<?php

use App\Actions\DeleteFund;
use App\Actions\EnsureFundCategories;
use App\Actions\GetFundProgress;
use App\Actions\ListUpcomingDues;
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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function makeFundData(array $overrides = []): FundData
{
    return FundData::from(array_merge([
        'name' => 'Moto Maintenance',
        'target_amount' => 400_000,
        'cadence' => 'cycle',
        'contribution_amount' => null,
        'category_id' => null,
        'next_due' => CarbonImmutable::now()->addMonths(2)->toDateString(),
        'due_interval_months' => 2,
        'notes' => null,
    ], $overrides));
}

function makeContributionData(array $overrides = []): FundContributionData
{
    return FundContributionData::from(array_merge([
        'amount' => 50_000,
        'date' => CarbonImmutable::now()->toDateString(),
        'description' => 'Cycle set-aside',
    ], $overrides));
}

test('creating a fund ensures the Maintenance and Taxes categories exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $fund = SaveFund::run(new SinkingFund, makeFundData());

    expect($fund->user_id)->toBe($user->id);

    $maintenance = $user->categories()->where('type', CategoryType::EXPENSE)->where('name', 'Maintenance')->first();
    $taxes = $user->categories()->where('type', CategoryType::EXPENSE)->where('name', 'Taxes')->first();

    expect($maintenance)->not->toBeNull()
        ->and($taxes)->not->toBeNull();

    // Idempotent — running again creates no duplicates.
    EnsureFundCategories::run($user);
    EnsureFundCategories::run($user);

    expect($user->categories()->where('name', 'Maintenance')->count())->toBe(1)
        ->and($user->categories()->where('name', 'Taxes')->count())->toBe(1);
});

test('fund category must be a user-owned expense category (validation)', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();

    $incomeCategory = Category::factory()->for($user)->create(['type' => CategoryType::INCOME, 'name' => 'Paycheck']);
    $foreignCategory = Category::factory()->for($intruder)->create(['type' => CategoryType::EXPENSE, 'name' => 'Foreign']);

    $this->actingAs($user)
        ->post(route('funds.store'), makeFundData(['category_id' => $incomeCategory->id])->toArray())
        ->assertSessionHasErrors('category_id');

    $this->actingAs($user)
        ->post(route('funds.store'), makeFundData(['category_id' => $foreignCategory->id])->toArray())
        ->assertSessionHasErrors('category_id');

    $expenseCategory = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
    $this->actingAs($user)
        ->post(route('funds.store'), makeFundData(['category_id' => $expenseCategory->id])->toArray())
        ->assertSessionHasNoErrors();
});

test('set-aside is pure: raises accumulated, creates no transaction, balances unchanged', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $fund = SaveFund::run(new SinkingFund, makeFundData());

    SaveFundContribution::run($fund, makeContributionData(['amount' => 50_000]));

    $progress = GetFundProgress::run($fund);

    expect($progress['accumulated'])->toBe(50_000)
        ->and($progress['percent'])->toBe(13); // 50_000 / 400_000 = 12.5 → 13

    expect(Transaction::count())->toBe(0)
        ->and($balance->fresh()->final_amount)->toBe(1_000_000)
        ->and(DB::table('fund_contributions')->where('type', 'contribution')->count())->toBe(1);
});

test('pay from fund is atomic: real expense, budget link, ledger withdrawal, balance sync, due roll', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $maintenance = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);
    $item = BudgetItem::factory()->for($budget)->for($maintenance)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    $nextDue = CarbonImmutable::now()->addMonths(2)->toDateString();
    $fund = SaveFund::run(new SinkingFund, makeFundData([
        'category_id' => $maintenance->id,
        'next_due' => $nextDue,
        'due_interval_months' => 2,
    ]));

    SaveFundContribution::run($fund, makeContributionData(['amount' => 200_000]));

    $contribution = PayFromFund::run($fund, makeContributionData([
        'amount' => 150_000,
        'description' => 'Servis Motor',
        'balance_id' => $balance->id,
    ]));

    // Real expense transaction exists, budget-linked via the resolver.
    $transaction = Transaction::query()->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->type)->toBe(CategoryType::EXPENSE)
        ->and($transaction->amount)->toBe(150_000)
        ->and($transaction->category_id)->toBe($maintenance->id)
        ->and($transaction->balance_id)->toBe($balance->id)
        ->and($transaction->budget_id)->toBe($budget->id)
        ->and($transaction->budget_item_id)->toBe($item->id)
        ->and($transaction->description)->toBe('Servis Motor');

    // Ledger withdrawal carries the transaction id; accumulated drops.
    expect($contribution->type)->toBe('withdrawal')
        ->and($contribution->transaction_id)->toBe($transaction->id)
        ->and(GetFundProgress::run($fund)['accumulated'])->toBe(50_000);

    // Paying balance really dropped.
    expect($balance->fresh()->final_amount)->toBe(850_000);

    // next_due rolled 2 months from the OLD due date.
    expect($fund->fresh()->next_due->toDateString())
        ->toBe(CarbonImmutable::parse($nextDue)->addMonthsNoOverflow(2)->toDateString());
});

test('pay from fund rejects amount above accumulated with insufficient_fund_reserve', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $fund = SaveFund::run(new SinkingFund, makeFundData());

    SaveFundContribution::run($fund, makeContributionData(['amount' => 50_000]));

    expect(fn () => PayFromFund::run($fund, makeContributionData([
        'amount' => 60_000,
        'balance_id' => $balance->id,
    ])))->toThrow(ValidationException::class, 'Insufficient fund reserve');

    // Nothing was created.
    expect(Transaction::count())->toBe(0)
        ->and(GetFundProgress::run($fund)['accumulated'])->toBe(50_000);
});

test('auto-contribution spreads the shortfall across months until due', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // 200k shortfall, due in 4 months → 50k/cycle.
    $fund = SaveFund::run(new SinkingFund, makeFundData([
        'target_amount' => 400_000,
        'next_due' => CarbonImmutable::now()->addMonths(4)->toDateString(),
    ]));
    SaveFundContribution::run($fund, makeContributionData(['amount' => 200_000]));

    expect(GetFundProgress::run($fund)['auto_contribution'])->toBe(50_000);

    // Overdue → full catch-up.
    $overdue = SaveFund::run(new SinkingFund, makeFundData([
        'target_amount' => 400_000,
        'next_due' => CarbonImmutable::now()->subDay()->toDateString(),
    ]));
    SaveFundContribution::run($overdue, makeContributionData(['amount' => 100_000]));

    expect(GetFundProgress::run($overdue)['auto_contribution'])->toBe(300_000);

    // No next_due → /12 fallback.
    $noDue = SaveFund::run(new SinkingFund, makeFundData([
        'next_due' => null,
    ]));

    expect(GetFundProgress::run($noDue)['auto_contribution'])->toBe(33_334); // ceil(400k/12)
});

test('status precedence: due_soon beats overfunded, underfunded, on_track', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Overfunded but due next week → due_soon.
    $fund = SaveFund::run(new SinkingFund, makeFundData([
        'target_amount' => 400_000,
        'next_due' => CarbonImmutable::now()->addDays(7)->toDateString(),
    ]));
    SaveFundContribution::run($fund, makeContributionData(['amount' => 400_000]));

    expect(GetFundProgress::run($fund)['status'])->toBe('due_soon');

    // Overfunded, no due → overfunded.
    $overfunded = SaveFund::run(new SinkingFund, makeFundData(['next_due' => null]));
    SaveFundContribution::run($overfunded, makeContributionData(['amount' => 450_000]));

    expect(GetFundProgress::run($overfunded)['status'])->toBe('overfunded');

    // Under 80% with a due within 60 days → underfunded.
    $underfunded = SaveFund::run(new SinkingFund, makeFundData([
        'next_due' => CarbonImmutable::now()->addDays(45)->toDateString(),
    ]));
    SaveFundContribution::run($underfunded, makeContributionData(['amount' => 200_000]));

    expect(GetFundProgress::run($underfunded)['status'])->toBe('underfunded');

    // Everything else → on_track.
    $onTrack = SaveFund::run(new SinkingFund, makeFundData([
        'next_due' => CarbonImmutable::now()->addMonths(6)->toDateString(),
    ]));
    SaveFundContribution::run($onTrack, makeContributionData(['amount' => 100_000]));

    expect(GetFundProgress::run($onTrack)['status'])->toBe('on_track');
});

test('soft-deleting a fund hides it but keeps the ledger', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $fund = SaveFund::run(new SinkingFund, makeFundData());
    SaveFundContribution::run($fund, makeContributionData(['amount' => 50_000]));

    DeleteFund::run($fund);

    expect(SinkingFund::count())->toBe(0)
        ->and(SinkingFund::withTrashed()->count())->toBe(1)
        ->and(DB::table('fund_contributions')->count())->toBe(1);
});

test('list upcoming dues returns horizon + overdue with shortfall math', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $dueSoon = SaveFund::run(new SinkingFund, makeFundData([
        'name' => 'Due Soon',
        'target_amount' => 400_000,
        'next_due' => CarbonImmutable::now()->addDays(10)->toDateString(),
        'due_interval_months' => 2,
    ]));
    SaveFundContribution::run($dueSoon, makeContributionData(['amount' => 200_000]));

    $overdue = SaveFund::run(new SinkingFund, makeFundData([
        'name' => 'Overdue',
        'target_amount' => 200_000,
        'next_due' => CarbonImmutable::now()->subDays(3)->toDateString(),
        'due_interval_months' => 12,
    ]));

    $farOut = SaveFund::run(new SinkingFund, makeFundData([
        'name' => 'Far Out',
        'next_due' => CarbonImmutable::now()->addMonths(6)->toDateString(),
    ]));

    $rows = ListUpcomingDues::run($user->id, 60);

    expect(collect($rows)->pluck('fund.name')->all())->toBe(['Overdue', 'Due Soon']);

    $overdueRow = collect($rows)->firstWhere('fund.name', 'Overdue');
    expect($overdueRow['days_until_due'])->toBeLessThan(0);

    // Overdue: full catch-up expected (0 months to due → 0 expected credits)
    // shortfall = 200k target − 0 accumulated − 0 = 200k.
    expect($overdueRow['projected_shortfall'])->toBe(200_000);

    $dueSoonRow = collect($rows)->firstWhere('fund.name', 'Due Soon');
    // auto slice = ceil(200k / 0 months?) — due in 10 days → months_to_due = 0
    // → full catch-up suggestion 200k, expected before due = 0 → shortfall 200k.
    expect($dueSoonRow['projected_shortfall'])->toBe(200_000);

    expect(collect($rows)->pluck('fund.name'))->not->toContain('Far Out');
});
