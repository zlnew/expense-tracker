<?php

use App\Actions\GetMonthlySpendingTrend;
use App\Actions\GetSummaryCardsData;
use App\Actions\SaveTransaction;
use App\Actions\TransferBetweenAccounts;
use App\DTO\TransactionData;
use App\DTO\TransferBetweenAccountsData;
use App\Enums\CategoryType;
use App\Mcp\Tools\GetBalanceSummaryTool;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\SinkingFund;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ============================================================================
// Ticket 1: Auto-Resolution for Budget Linking & Ghost Transaction Elimination
// ============================================================================

test('ticket 1: posting transaction with category_id alone auto-links to active budget item', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 500_000, 'final_amount' => 500_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Groceries', 'type' => CategoryType::EXPENSE]);

    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);

    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 400_000,
    ]);

    Sanctum::actingAs($user, ['transactions:write', 'transactions:read']);

    $response = $this->postJson('/api/transactions', [
        'balance_id' => $balance->id,
        'category_id' => $category->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => now()->toDateString(),
        'amount' => 50_000,
        'description' => 'Supermarket run',
    ]);

    $response->assertCreated()
        ->assertJsonPath('budget_id', $budget->id)
        ->assertJsonPath('budget_item_id', $item->id);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category_id' => $category->id,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'amount' => 50_000,
    ]);
});

test('ticket 1: posting transaction with unbudgeted category auto-creates planned 0 envelope and links it', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 500_000, 'final_amount' => 500_000]);
    $unbudgetedCategory = Category::factory()->for($user)->create(['name' => 'Books', 'type' => CategoryType::EXPENSE]);

    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);

    // Budget has no envelope for Books initially
    expect(BudgetItem::where('budget_id', $budget->id)->where('category_id', $unbudgetedCategory->id)->exists())->toBeFalse();

    Sanctum::actingAs($user, ['transactions:write', 'transactions:read']);

    $response = $this->postJson('/api/transactions', [
        'balance_id' => $balance->id,
        'category_id' => $unbudgetedCategory->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => now()->toDateString(),
        'amount' => 75_000,
        'description' => 'Sci-fi novel',
    ]);

    $response->assertCreated()
        ->assertJsonPath('budget_id', $budget->id);

    $createdItem = BudgetItem::where('budget_id', $budget->id)
        ->where('category_id', $unbudgetedCategory->id)
        ->first();

    expect($createdItem)->not->toBeNull();
    expect($createdItem->planned_amount)->toBe(0);
    expect($response->json('budget_item_id'))->toBe($createdItem->id);
});

test('ticket 1: SaveTransaction directly auto-resolves active budget when omitted', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 500_000, 'final_amount' => 500_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Coffee', 'type' => CategoryType::EXPENSE]);

    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);

    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 200_000,
    ]);

    $this->actingAs($user);

    $txn = SaveTransaction::run(new Transaction, TransactionData::from([
        'balance_id' => $balance->id,
        'category_id' => $category->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => now()->toDateString(),
        'amount' => 35_000,
        'description' => 'Latte',
    ]));

    expect($txn->budget_id)->toBe($budget->id);
    expect($txn->budget_item_id)->toBe($item->id);
});

// ============================================================================
// Ticket 2: Separate Internal Movement from Gross Income & Operating Expenses
// ============================================================================

test('ticket 2: internal balance transfers are identified as is_transfer and excluded from income and expense summaries', function () {
    $user = User::factory()->create();
    $source = Balance::factory()->for($user)->create(['name' => 'BCA Xpresi', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $destination = Balance::factory()->for($user)->create(['name' => 'SeaBank', 'initial_amount' => 200_000, 'final_amount' => 200_000]);

    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);

    // External real income: Salary Rp 5.000.000
    $salaryCategory = Category::factory()->for($user)->create(['name' => 'Salary', 'type' => CategoryType::INCOME]);
    SaveTransaction::run(new Transaction, TransactionData::from([
        'user_id' => $user->id,
        'balance_id' => $source->id,
        'budget_id' => $budget->id,
        'category_id' => $salaryCategory->id,
        'type' => CategoryType::INCOME->value,
        'date' => now()->toDateString(),
        'amount' => 5_000_000,
        'description' => 'Monthly Salary',
    ]));

    // External real expense: Rent Rp 1.500.000
    $rentCategory = Category::factory()->for($user)->create(['name' => 'Rent', 'type' => CategoryType::EXPENSE]);
    SaveTransaction::run(new Transaction, TransactionData::from([
        'user_id' => $user->id,
        'balance_id' => $source->id,
        'budget_id' => $budget->id,
        'category_id' => $rentCategory->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => now()->toDateString(),
        'amount' => 1_500_000,
        'description' => 'Apartment Rent',
    ]));

    // Perform internal transfer: Sweep Rp 500.000 from BCA -> SeaBank
    TransferBetweenAccounts::run(TransferBetweenAccountsData::from([
        'from_account_id' => $source->id,
        'to_account_id' => $destination->id,
        'amount' => 500_000,
        'date' => now()->toDateString(),
        'description' => 'Savings Sweep',
    ]));

    // Assert balances updated correctly
    expect($source->fresh()->final_amount)->toBe(1_000_000 + 5_000_000 - 1_500_000 - 500_000);
    expect($destination->fresh()->final_amount)->toBe(200_000 + 500_000);

    // Verify transfer transactions have is_transfer = true
    $transfers = Transaction::where('user_id', $user->id)
        ->whereNotNull('transfer_group_id')
        ->whereNull('category_id')
        ->get();

    expect($transfers)->toHaveCount(2);
    foreach ($transfers as $t) {
        expect($t->is_transfer)->toBeTrue();
    }

    // Check Summary Cards: Gross income must be ONLY external earnings (5_000_000), NOT 5_500_000!
    $summary = GetSummaryCardsData::run($user);
    expect($summary['current_month_incomes'])->toBe(5_000_000);

    // Current month expenses must be ONLY real operating expenses (1_500_000), NOT 2_000_000!
    expect($summary['current_month_expenses'])->toBe(1_500_000);
});

test('ticket 2: monthly spending trend excludes internal transfer spikes', function () {
    $user = User::factory()->create();
    $accA = Balance::factory()->for($user)->create(['initial_amount' => 10_000_000, 'final_amount' => 10_000_000]);
    $accB = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);

    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfYear(),
        'period_end' => now()->endOfYear(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);

    // Real expense: 100_000
    $cat = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE]);
    SaveTransaction::run(new Transaction, TransactionData::from([
        'user_id' => $user->id,
        'balance_id' => $accA->id,
        'budget_id' => $budget->id,
        'category_id' => $cat->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => now()->toDateString(),
        'amount' => 100_000,
    ]));

    // Transfer: 5_000_000 sweep
    TransferBetweenAccounts::run(TransferBetweenAccountsData::from([
        'from_account_id' => $accA->id,
        'to_account_id' => $accB->id,
        'amount' => 5_000_000,
        'date' => now()->toDateString(),
        'description' => 'Big Sweep',
    ]));

    $trend = GetMonthlySpendingTrend::run($user);
    $currentMonthIndex = now()->month - 1;

    // The trend expense for this month should only be 100_000, not 5_100_000!
    expect($trend[$currentMonthIndex]['expense'])->toBe(100_000);
    // The trend income for this month should be 0, not 5_000_000!
    expect($trend[$currentMonthIndex]['income'])->toBe(0);
});

// ============================================================================
// Ticket 3: Promote Real Balance to Core Model & REST API
// ============================================================================

test('ticket 3: Balance model has real_balance attribute subtracting sinking fund reserves', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'name' => 'BCA Tahapan',
        'initial_amount' => 10_000_000,
        'final_amount' => 10_000_000,
    ]);

    // Create a sinking fund backed by this balance with Rp 3.000.000 reserved
    $fund = SinkingFund::factory()->for($user)->create([
        'name' => 'Holiday Trip',
        'from_balance_id' => $balance->id,
        'target_amount' => 5_000_000,
    ]);

    $fund->contributions()->create([
        'user_id' => $user->id,
        'amount' => 3_000_000,
        'date' => now()->toDateString(),
        'type' => 'contribution',
    ]);

    // Direct Eloquent property access
    expect($balance->real_balance)->toBe(7_000_000);

    // Array / JSON serialization includes real_balance
    $array = $balance->toArray();
    expect($array)->toHaveKey('real_balance');
    expect($array['real_balance'])->toBe(7_000_000);
});

test('ticket 3: GET /api/balances and GET /api/balances/{id} return real_balance', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'name' => 'BCA Tahapan',
        'initial_amount' => 8_000_000,
        'final_amount' => 8_000_000,
    ]);

    $fund = SinkingFund::factory()->for($user)->create([
        'name' => 'Gadget Fund',
        'from_balance_id' => $balance->id,
        'target_amount' => 10_000_000,
    ]);

    $fund->contributions()->create([
        'user_id' => $user->id,
        'amount' => 2_500_000,
        'date' => now()->toDateString(),
        'type' => 'contribution',
    ]);

    Sanctum::actingAs($user, ['balances:read']);

    // List endpoint
    $this->getJson('/api/balances')
        ->assertOk()
        ->assertJsonPath('0.id', $balance->id)
        ->assertJsonPath('0.final_amount', 8_000_000)
        ->assertJsonPath('0.reserved', 2_500_000)
        ->assertJsonPath('0.real_balance', 5_500_000)
        ->assertJsonPath('0.real', 5_500_000);

    // Single item endpoint
    $this->getJson("/api/balances/{$balance->id}")
        ->assertOk()
        ->assertJsonPath('id', $balance->id)
        ->assertJsonPath('final_amount', 8_000_000)
        ->assertJsonPath('reserved', 2_500_000)
        ->assertJsonPath('real_balance', 5_500_000)
        ->assertJsonPath('real', 5_500_000);
});

test('ticket 3: MCP get_balance_summary passes through real_balance', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'name' => 'Main Account',
        'initial_amount' => 5_000_000,
        'final_amount' => 5_000_000,
    ]);

    $fund = SinkingFund::factory()->for($user)->create([
        'name' => 'Emergency Fund',
        'from_balance_id' => $balance->id,
        'target_amount' => 10_000_000,
    ]);

    $fund->contributions()->create([
        'user_id' => $user->id,
        'amount' => 1_200_000,
        'date' => now()->toDateString(),
        'type' => 'contribution',
    ]);

    $tool = new GetBalanceSummaryTool;
    $result = $tool->execute($user, []);

    expect($result['content'][0]['text'])->toContain('"real_balance": 3800000');
    expect($result['content'][0]['text'])->toContain('"total_net_worth": 3800000');
});
