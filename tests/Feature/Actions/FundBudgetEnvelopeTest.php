<?php

use App\Actions\CheckBudgetAlerts;
use App\Actions\DeleteFund;
use App\Actions\GetBudgetProgress;
use App\Actions\GetExpenseBreakdown;
use App\Actions\PayFromFund;
use App\Actions\SaveFund;
use App\Actions\SaveFundContribution;
use App\DTO\FundContributionData;
use App\DTO\FundData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\SinkingFund;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BudgetRollover;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Envelope-basis funds ↔ budget interaction (spec 2026-08-16):
 * set-asides count toward budget actuals, payouts are budget-exempt.
 *
 * All budgets use cutoff_day => 1 so cycle windows are trivial: the current
 * cycle always contains now(), and a date two months back is always OUTSIDE
 * the current cycle (the cycle guard tests rely on that).
 */
function envelopeFundData(array $overrides = []): FundData
{
    $user = Auth::user() ?? User::query()->latest('id')->first();
    $defaults = [
        'name' => 'Moto Maintenance',
        'target_amount' => 400_000,
        'cadence' => 'cycle',
        'contribution_amount' => null,
        'next_due' => CarbonImmutable::now()->addMonths(2)->toDateString(),
        'due_interval_months' => 2,
        'notes' => null,
    ];
    if ($user) {
        if (! array_key_exists('category_id', $overrides)) {
            $cat = Category::query()->where('user_id', $user->id)->where('type', CategoryType::EXPENSE->value)->first();
            if (! $cat) {
                $cat = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
            }
            $defaults['category_id'] = $cat->id;
        } else {
            $defaults['category_id'] = $overrides['category_id'];
            unset($overrides['category_id']);
        }
        if (! array_key_exists('from_balance_id', $overrides)) {
            $bal = Balance::query()->where('user_id', $user->id)->first();
            if (! $bal) {
                $bal = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
            }
            $defaults['from_balance_id'] = $bal->id;
        }
    } else {
        $defaults['category_id'] = null;
        $defaults['from_balance_id'] = null;
    }
    $merged = array_merge($defaults, $overrides);
    if (array_key_exists('category_id', $overrides)) {
        $merged['category_id'] = $overrides['category_id'];
    }
    if (array_key_exists('from_balance_id', $overrides)) {
        $merged['from_balance_id'] = $overrides['from_balance_id'];
    }

    return FundData::from($merged);
}

function envelopeContributionData(array $overrides = []): FundContributionData
{
    return FundContributionData::from(array_merge([
        'amount' => 50_000,
        'date' => CarbonImmutable::now()->toDateString(),
        'description' => 'Cycle set-aside',
    ], $overrides));
}

function envelopeExpenseCategory(User $user, string $name = 'Maintenance'): Category
{
    return Category::factory()->for($user)->create([
        'type' => CategoryType::EXPENSE,
        'name' => $name,
    ]);
}

/**
 * @return array{Budget, BudgetItem, Category}
 */
function envelopeBudget(User $user, int $planned = 1_000_000, ?Category $category = null): array
{
    $category ??= envelopeExpenseCategory($user);

    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 1,
        'is_active' => true,
    ]);

    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => $planned,
    ]);

    return [$budget, $item, $category];
}

function envelopeFund(User $user, Category $category): SinkingFund
{
    return SaveFund::run(new SinkingFund, envelopeFundData(['category_id' => $category->id]));
}

function envelopeItemActual(int $userId, int $itemId): int
{
    $row = GetBudgetProgress::run($userId)
        ->toCollection()
        ->first(fn ($row) => $row->id === $itemId);

    return $row?->actual_amount ?? 0;
}

// ─────────────────────────────────────────────────────────────────────────────

test('E1 set-aside counts toward budget actuals — no transaction row, balance untouched', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    [$budget, $item] = envelopeBudget($user, 1_000_000);
    $fund = envelopeFund($user, $budget->expenses->first()->category);

    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 300_000]));

    $progress = GetBudgetProgress::run($user);
    $row = $progress->toCollection()->first(fn ($r) => $r->id === $item->id);

    expect($row->actual_amount)->toBe(300_000)
        ->and($row->diff_amount)->toBe(700_000)
        ->and(Transaction::count())->toBe(0)
        ->and($balance->fresh()->final_amount)->toBe(1_000_000);
});

test('E2a payout is budget-exempt: set-aside 12M + payout 12M → actuals exactly 12M (double-subtract trap)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 30_000_000, 'final_amount' => 30_000_000]);
    [$budget, $item, $category] = envelopeBudget($user, 1_000_000);
    $fund = envelopeFund($user, $category);

    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 12_000_000]));
    PayFromFund::run($fund, envelopeContributionData(['amount' => 12_000_000, 'balance_id' => $balance->id]));

    // The double-subtraction trap: subtracting the payout ON TOP of the
    // id-exclusion would give 0; the old cash-basis double-count gave 24M.
    expect(envelopeItemActual($user->id, $item->id))->toBe(12_000_000);
});

test('E2b payout smaller than the reserve: set-aside 12M + payout 8M → actuals 12M', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 30_000_000, 'final_amount' => 30_000_000]);
    [$budget, $item, $category] = envelopeBudget($user, 1_000_000);
    $fund = envelopeFund($user, $category);

    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 12_000_000]));
    PayFromFund::run($fund, envelopeContributionData(['amount' => 8_000_000, 'balance_id' => $balance->id]));

    expect(envelopeItemActual($user->id, $item->id))->toBe(12_000_000);
});

test('E2c payout with NO in-cycle set-asides → actuals 0 (only in-cycle reservations count)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 30_000_000, 'final_amount' => 30_000_000]);
    [$budget, $item, $category] = envelopeBudget($user, 1_000_000);
    $fund = envelopeFund($user, $category);

    // Reserve was built OUTSIDE the current cycle; nothing reserved in-cycle.
    SaveFundContribution::run($fund, envelopeContributionData([
        'amount' => 12_000_000,
        'date' => CarbonImmutable::now()->subMonthsNoOverflow(2)->toDateString(),
    ]));
    PayFromFund::run($fund, envelopeContributionData(['amount' => 8_000_000, 'balance_id' => $balance->id]));

    expect(envelopeItemActual($user->id, $item->id))->toBe(0);
});

test('E3 regular expense + set-aside mix: 200k expense + 1M set-aside → actuals 1.2M', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 5_000_000, 'final_amount' => 5_000_000]);
    [$budget, $item, $category] = envelopeBudget($user, 1_000_000);
    $fund = envelopeFund($user, $category);

    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 1_000_000]));

    Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 200_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => now(),
    ]);

    expect(envelopeItemActual($user->id, $item->id))->toBe(1_200_000);
});

test('E4 fund category with no budget item → set-aside counts nowhere, no crash', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $orphanCategory = envelopeExpenseCategory($user, 'Orphan');
    [$budget, $item, $category] = envelopeBudget($user, 1_000_000);
    $fund = envelopeFund($user, $orphanCategory);

    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 500_000]));

    // The budget item's actual stays 0 and nothing crashed.
    expect(envelopeItemActual($user->id, $item->id))->toBe(0);
});

test('E5 soft-deleted fund: set-asides stop counting, payout transaction stays excluded', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 5_000_000, 'final_amount' => 5_000_000]);
    [$budget, $item, $category] = envelopeBudget($user, 1_000_000);
    $fund = envelopeFund($user, $category);

    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 300_000]));
    PayFromFund::run($fund, envelopeContributionData(['amount' => 100_000, 'balance_id' => $balance->id]));

    expect(envelopeItemActual($user->id, $item->id))->toBe(300_000);

    DeleteFund::run($fund);

    // reservedPerItem scopes to non-deleted funds (set-aside drops out);
    // payoutTransactionIds is NOT scoped (the payout stays exempt).
    expect(envelopeItemActual($user->id, $item->id))->toBe(0);
});

test('E6 GetExpenseBreakdown parity: set-aside 300k + expense 700k → 30%/70%', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 5_000_000, 'final_amount' => 5_000_000]);
    $catA = envelopeExpenseCategory($user, 'Cat A');
    $catB = envelopeExpenseCategory($user, 'Cat B');

    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 1,
        'is_active' => true,
    ]);
    $itemA = BudgetItem::factory()->for($budget)->for($catA)->create(['type' => CategoryType::EXPENSE, 'planned_amount' => 1_000_000]);
    $itemB = BudgetItem::factory()->for($budget)->for($catB)->create(['type' => CategoryType::EXPENSE, 'planned_amount' => 1_000_000]);

    $fundA = envelopeFund($user, $catA);
    SaveFundContribution::run($fundA, envelopeContributionData(['amount' => 300_000]));

    Transaction::factory()->for($user)->for($balance)->for($catB)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 700_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $itemB->id,
        'date' => now(),
    ]);

    $breakdown = collect(GetExpenseBreakdown::run($user))->keyBy('category');

    expect($breakdown['Cat A']['amount'])->toBe(300_000)
        ->and($breakdown['Cat B']['amount'])->toBe(700_000)
        ->and($breakdown['Cat A']['percentage'])->toBe(30.0)
        ->and($breakdown['Cat B']['percentage'])->toBe(70.0);
});

test('E7a rollover parity: set-aside 1M vs planned 1M → leftover 0 (reserved money does not roll)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = envelopeExpenseCategory($user);

    $previous = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth()->subMonthNoOverflow(),
        'period_end' => now()->startOfMonth()->subMonthNoOverflow()->endOfMonth(),
        'cutoff_day' => 1,
    ]);
    BudgetItem::factory()->for($previous)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 1_000_000,
    ]);

    $fund = envelopeFund($user, $category);
    SaveFundContribution::run($fund, envelopeContributionData([
        'amount' => 1_000_000,
        'date' => now()->startOfMonth()->subDay()->toDateString(), // inside the previous period
    ]));

    $next = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 1,
    ]);

    expect(BudgetRollover::leftovers($next))->toBe([]);
});

test('E7b rollover parity: set-aside 1M + payout 12M in the period → leftover 0 (no negative clamp)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 30_000_000, 'final_amount' => 30_000_000]);
    $category = envelopeExpenseCategory($user);

    $previous = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth()->subMonthNoOverflow(),
        'period_end' => now()->startOfMonth()->subMonthNoOverflow()->endOfMonth(),
        'cutoff_day' => 1,
    ]);
    BudgetItem::factory()->for($previous)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 1_000_000,
    ]);

    $fund = envelopeFund($user, $category);
    $inPeriod = now()->startOfMonth()->subDay()->toDateString();

    // 11M reserve built before the period + 1M set aside inside it.
    SaveFundContribution::run($fund, envelopeContributionData([
        'amount' => 11_000_000,
        'date' => CarbonImmutable::now()->subMonthsNoOverflow(2)->toDateString(),
    ]));
    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 1_000_000, 'date' => $inPeriod]));
    PayFromFund::run($fund, envelopeContributionData(['amount' => 12_000_000, 'balance_id' => $balance->id, 'date' => $inPeriod]));

    $next = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 1,
    ]);

    expect(BudgetRollover::leftovers($next))->toBe([]);
});

test('E8 alerts — payout is silent: set-aside below 80%, payout → nothing sent, flags untouched', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => 'https://discord.com/api/webhooks/123']);
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 5_000_000, 'final_amount' => 5_000_000]);
    [$budget, $item, $category] = envelopeBudget($user, 100_000);
    $fund = envelopeFund($user, $category);

    // In-cycle set-aside stays below 80% (50k of 100k)…
    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 50_000]));
    // …reserve built out-of-cycle so the 100k payout is allowed…
    SaveFundContribution::run($fund, envelopeContributionData([
        'amount' => 100_000,
        'date' => CarbonImmutable::now()->subMonthsNoOverflow(2)->toDateString(),
    ]));
    // …and the payout itself must NOT fire (budget-exempt — call removed).
    PayFromFund::run($fund, envelopeContributionData(['amount' => 100_000, 'balance_id' => $balance->id]));

    Http::assertNothingSent();
    expect($item->fresh()->alert_80_sent)->toBeFalse()
        ->and($item->fresh()->alert_100_sent)->toBeFalse();
});

test('E9 alerts — set-aside fires 80% then 100%', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => 'https://discord.com/api/webhooks/123']);
    $this->actingAs($user);

    [$budget, $item, $category] = envelopeBudget($user, 100_000);
    $fund = envelopeFund($user, $category);

    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 80_000]));

    Http::assertSentCount(1);
    expect($item->fresh()->alert_80_sent)->toBeTrue()
        ->and($item->fresh()->alert_100_sent)->toBeFalse();

    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 20_000]));

    Http::assertSentCount(2);
    expect($item->fresh()->alert_100_sent)->toBeTrue();
});

test('E10 alerts — cycle guard: back-dated set-aside fires nothing', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => 'https://discord.com/api/webhooks/123']);
    $this->actingAs($user);

    [$budget, $item, $category] = envelopeBudget($user, 100_000);
    $fund = envelopeFund($user, $category);

    SaveFundContribution::run($fund, envelopeContributionData([
        'amount' => 200_000,
        'date' => CarbonImmutable::now()->subMonthsNoOverflow(2)->toDateString(),
    ]));

    Http::assertNothingSent();
    expect($item->fresh()->alert_80_sent)->toBeFalse();
});

test('E11 alerts — regular expense uses envelope actuals, message says "used"', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => 'https://discord.com/api/webhooks/123']);
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 5_000_000, 'final_amount' => 5_000_000]);
    [$budget, $item, $category] = envelopeBudget($user, 100_000);
    $fund = envelopeFund($user, $category);

    // 60k reserved (no fire yet)…
    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 60_000]));
    Http::assertNothingSent();

    // …then a 20k regular expense crosses 80% via envelope actuals.
    $transaction = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 20_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => now(),
    ]);

    CheckBudgetAlerts::run($user, $transaction);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request['content'], '80%')
        && str_contains($request['content'], 'used')
        && ! str_contains($request['content'], 'spent'));
});

test('E12 BudgetApiController (Sanctum): actuals envelope-aware, payout excluded, shape unchanged', function () {
    $user = User::factory()->create();
    $this->actingAs($user); // SaveFund associates the auth user
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 5_000_000, 'final_amount' => 5_000_000]);
    [$budget, $item, $category] = envelopeBudget($user, 1_000_000);
    $fund = envelopeFund($user, $category);

    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 300_000]));
    PayFromFund::run($fund, envelopeContributionData(['amount' => 100_000, 'balance_id' => $balance->id]));

    Sanctum::actingAs($user, ['budgets:read']);
    $response = $this->getJson('/api/budgets')->assertOk();

    $apiBudget = collect($response->json())->firstWhere('id', $budget->id);
    $apiItem = collect($apiBudget['items'])->firstWhere('id', $item->id);

    expect($apiItem['actual_amount'])->toBe(300_000)
        ->and($apiItem['diff_amount'])->toBe(700_000);
});

test('E13 web endpoint: envelope extras in the response, frozen /api/transactions contract untouched', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 5_000_000, 'final_amount' => 5_000_000]);
    [$budget, $item, $category] = envelopeBudget($user, 1_000_000);
    $fund = envelopeFund($user, $category);

    SaveFundContribution::run($fund, envelopeContributionData(['amount' => 300_000]));
    $payout = PayFromFund::run($fund, envelopeContributionData(['amount' => 100_000, 'balance_id' => $balance->id]));

    // NOTE: month/year query params hit TransactionQuery's Postgres-only
    // cycle SQL (EXTRACT/INTERVAL), which the SQLite test suite cannot run —
    // pre-existing platform constraint. This exercises the controller's
    // fallback window (active budget's current cycle), which is the same
    // envelope computation the client consumes when it omits month/year.
    $this->getJson(route('budgets.transactions', $budget))
        ->assertOk()
        ->assertJsonPath('fund.reserved.'.$item->id, 300_000)
        ->assertJsonPath('fund.payout_transaction_ids.0', $payout->transaction_id)
        ->assertJsonCount(1, 'transactions')
        ->assertJsonPath('transactions.0.id', $payout->transaction_id);

    // Frozen /api/transactions contract: still the bare TransactionData array.
    Sanctum::actingAs($user, ['transactions:read']);
    $apiResponse = $this->getJson('/api/transactions')->assertOk();

    expect($apiResponse->json())->toBeArray()
        ->and($apiResponse->json())->not->toHaveKey('fund')
        ->and($apiResponse->json())->not->toHaveKey('transactions');
});

test('E14 transaction with null budget_item_id is resolved by category_id to its envelope', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 5_000_000, 'final_amount' => 5_000_000]);
    [$budget, $item, $category] = envelopeBudget($user, 1_000_000);

    // Create an expense transaction without budget_item_id (e.g. from quick log or external import)
    Transaction::forceCreate([
        'user_id' => $user->id,
        'balance_id' => $balance->id,
        'budget_id' => $budget->id,
        'budget_item_id' => null,
        'category_id' => $category->id,
        'type' => CategoryType::EXPENSE->value,
        'amount' => 250_000,
        'date' => CarbonImmutable::now()->toDateString(),
        'description' => 'Quick unlinked expense',
    ]);

    expect(envelopeItemActual($user->id, $item->id))->toBe(250_000);
});
