<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bulk store saves every transaction', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create();
    $budget = Budget::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create();
    $budgetItem = BudgetItem::factory()->for($budget)->for($category)->create();

    $payload = collect(range(1, 3))->map(fn (int $i) => [
        'balance_id' => $balance->id,
        'budget_id' => $budget->id,
        'budget_item_id' => $budgetItem->id,
        'category_id' => $category->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => now()->toDateString(),
        'amount' => 25_000 * $i,
        'description' => "Bulk item {$i}",
    ])->all();

    $this->actingAs($user)
        ->post(route('transactions.bulk-store'), ['items' => $payload])
        ->assertRedirect();

    expect(Transaction::count())->toBe(3);

    $amounts = Transaction::query()->orderBy('amount')->pluck('amount')->all();
    expect($amounts)->toBe([25_000, 50_000, 75_000]);
});
