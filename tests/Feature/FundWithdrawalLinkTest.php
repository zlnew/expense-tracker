<?php

use App\Actions\DeleteFundContribution;
use App\Actions\DeleteTransaction;
use App\Actions\GetFundProgress;
use App\Actions\PayFromFund;
use App\Actions\SaveFund;
use App\Actions\SaveFundContribution;
use App\DTO\FundContributionData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\FundContribution;
use App\Models\SinkingFund;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BudgetActuals;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('withdrawal posts a linked expense with the same group_id on both legs', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'is_active' => true,
    ]);
    BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    $fund = SaveFund::run(new SinkingFund, \App\DTO\FundData::from([
        'name' => 'Moto', 'target_amount' => 400_000, 'cadence' => 'cycle',
        'contribution_amount' => null, 'category_id' => $category->id,
        'next_due' => CarbonImmutable::now()->addMonths(2)->toDateString(),
        'due_interval_months' => 2, 'notes' => null,
    ]));
    SaveFundContribution::run($fund, FundContributionData::from(['amount' => 200_000, 'date' => CarbonImmutable::now()->toDateString()]));

    $withdrawal = PayFromFund::run($fund, FundContributionData::from([
        'amount' => 150_000, 'date' => CarbonImmutable::now()->toDateString(),
        'description' => 'Servis', 'balance_id' => $balance->id,
    ]));

    $tx = Transaction::first();

    expect($withdrawal->group_id)->not->toBeNull()
        ->and($tx->transfer_group_id)->toBe($withdrawal->group_id)
        ->and($tx->transfer_group_id)->not->toBeEmpty()
        ->and($withdrawal->transaction_id)->toBe($tx->id)
        ->and($tx->budget_id)->not->toBeNull()
        ->and($tx->budget_item_id)->not->toBeNull();
});

test('reserve drops and balance syncs in one atomic withdrawal', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
    $fund = SaveFund::run(new SinkingFund, \App\DTO\FundData::from([
        'name' => 'Moto', 'target_amount' => 400_000, 'cadence' => 'cycle',
        'contribution_amount' => null, 'category_id' => $category->id,
        'next_due' => null, 'due_interval_months' => 2, 'notes' => null,
    ]));
    SaveFundContribution::run($fund, FundContributionData::from(['amount' => 200_000, 'date' => CarbonImmutable::now()->toDateString()]));

    PayFromFund::run($fund, FundContributionData::from([
        'amount' => 150_000, 'date' => CarbonImmutable::now()->toDateString(),
        'balance_id' => $balance->id,
    ]));

    expect(GetFundProgress::run($fund)['accumulated'])->toBe(50_000)
        ->and($balance->fresh()->final_amount)->toBe(850_000);
});

test('empty reserve still 422s on withdrawal', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
    $fund = SaveFund::run(new SinkingFund, \App\DTO\FundData::from([
        'name' => 'Moto', 'target_amount' => 400_000, 'cadence' => 'cycle',
        'contribution_amount' => null, 'category_id' => $category->id,
        'next_due' => null, 'due_interval_months' => 2, 'notes' => null,
    ]));

    expect(fn () => PayFromFund::run($fund, FundContributionData::from([
        'amount' => 10_000, 'date' => CarbonImmutable::now()->toDateString(), 'balance_id' => $balance->id,
    ])))->toThrow(ValidationException::class, 'Insufficient fund reserve');
});

test('withdrawal expense is budget-linked but excluded from envelope actuals (no double spend)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'is_active' => true,
    ]);
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE, 'planned_amount' => 500_000,
    ]);

    $fund = SaveFund::run(new SinkingFund, \App\DTO\FundData::from([
        'name' => 'Moto', 'target_amount' => 400_000, 'cadence' => 'cycle',
        'contribution_amount' => null, 'category_id' => $category->id,
        'next_due' => null, 'due_interval_months' => 2, 'notes' => null,
    ]));

    // Set aside in this budget window — counts as reserved for this item.
    SaveFundContribution::run($fund, FundContributionData::from([
        'amount' => 100_000, 'date' => now()->toDateString(),
    ]));

    $start = CarbonImmutable::parse($budget->period_start);
    $end = CarbonImmutable::parse($budget->period_end);
    expect(BudgetActuals::perItem($user, $budget, $start, $end)[$item->id] ?? 0)->toBe(100_000);

    // Pay out — the expense is linked but the id is excluded; actuals stay 100k (envelope exempt).
    PayFromFund::run($fund, FundContributionData::from([
        'amount' => 60_000, 'date' => now()->toDateString(), 'balance_id' => $balance->id,
    ]));

    expect(BudgetActuals::perItem($user, $budget, $start, $end)[$item->id] ?? 0)->toBe(100_000);
});

test('deleting the linked expense without cascade returns 409', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
    $fund = SaveFund::run(new SinkingFund, \App\DTO\FundData::from([
        'name' => 'Moto', 'target_amount' => 400_000, 'cadence' => 'cycle',
        'contribution_amount' => null, 'category_id' => $category->id, 'next_due' => null, 'due_interval_months' => 2, 'notes' => null,
    ]));
    SaveFundContribution::run($fund, FundContributionData::from(['amount' => 200_000, 'date' => CarbonImmutable::now()->toDateString()]));
    PayFromFund::run($fund, FundContributionData::from(['amount' => 50_000, 'date' => CarbonImmutable::now()->toDateString(), 'balance_id' => $balance->id]));

    $tx = Transaction::first();

    // Web path: expects 409
    $this->delete(route('transactions.destroy', $tx))->assertStatus(409);

    // API path: also 409 (Sanctum)
    $this->deleteJson(route('api.transactions.destroy', $tx))->assertStatus(409);
});

test('deleting with cascade removes both the expense and its withdrawal', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
    $fund = SaveFund::run(new SinkingFund, \App\DTO\FundData::from([
        'name' => 'Moto', 'target_amount' => 400_000, 'cadence' => 'cycle',
        'contribution_amount' => null, 'category_id' => $category->id, 'next_due' => null, 'due_interval_months' => 2, 'notes' => null,
    ]));
    SaveFundContribution::run($fund, FundContributionData::from(['amount' => 200_000, 'date' => CarbonImmutable::now()->toDateString()]));
    PayFromFund::run($fund, FundContributionData::from(['amount' => 50_000, 'date' => CarbonImmutable::now()->toDateString(), 'balance_id' => $balance->id]));

    $tx = Transaction::first();
    $groupId = $tx->transfer_group_id;

    DeleteTransaction::run($tx);

    expect(Transaction::count())->toBe(0)
        ->and(FundContribution::where('group_id', $groupId)->count())->toBe(0)
        ->and(GetFundProgress::run($fund)['accumulated'])->toBe(200_000)
        ->and($balance->fresh()->final_amount)->toBe(1_000_000);
});

test('deleting the withdrawal without cascade returns 409, with cascade removes the expense', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
    $fund = SaveFund::run(new SinkingFund, \App\DTO\FundData::from([
        'name' => 'Moto', 'target_amount' => 400_000, 'cadence' => 'cycle',
        'contribution_amount' => null, 'category_id' => $category->id, 'next_due' => null, 'due_interval_months' => 2, 'notes' => null,
    ]));
    SaveFundContribution::run($fund, FundContributionData::from(['amount' => 200_000, 'date' => CarbonImmutable::now()->toDateString()]));
    $withdrawal = PayFromFund::run($fund, FundContributionData::from(['amount' => 50_000, 'date' => CarbonImmutable::now()->toDateString(), 'balance_id' => $balance->id]));

    // API guard on the withdrawal leg
    $this->deleteJson(route('api.fund-contributions.destroy', $withdrawal))->assertStatus(409);

    $contributionId = $withdrawal->id;
    $groupId = $withdrawal->group_id;

    DeleteFundContribution::run($withdrawal->fresh());

    expect(FundContribution::where('id', $contributionId)->exists())->toBeFalse()
        ->and(Transaction::where('transfer_group_id', $groupId)->exists())->toBeFalse();
});
