<?php

use App\Actions\DeleteTransaction;
use App\Actions\SaveBudget;
use App\Actions\TransferBetweenAccounts;
use App\DTO\BudgetData;
use App\DTO\TransferBetweenAccountsData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('transfer moves funds between two balances and links both legs', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->for($user)->create();
    $source = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 500_000, 'final_amount' => 500_000]);
    $destination = Balance::factory()->for($user)->create(['name' => 'Bank', 'initial_amount' => 100_000, 'final_amount' => 100_000]);

    TransferBetweenAccounts::run(new TransferBetweenAccountsData(
        from_account_id: $source->id,
        to_account_id: $destination->id,
        date: CarbonImmutable::now(),
        amount: 200_000,
        description: 'split',
    ));

    $source->refresh();
    $destination->refresh();
    expect($source->final_amount)->toBe(300_000)
        ->and($destination->final_amount)->toBe(300_000);

    $legs = Transaction::where('transfer_group_id', '!=', null)->get();
    expect($legs)->toHaveCount(2)
        ->and($legs->pluck('transfer_group_id')->unique())->toHaveCount(1)
        ->and($legs->pluck('amount')->all())->toBe([200_000, 200_000])
        ->and($legs->pluck('type')->map(fn ($t) => $t->value)->sort()->values()->all())->toBe([CategoryType::EXPENSE->value, CategoryType::INCOME->value]);
});

test('transfer rejects insufficient funds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $source = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 50_000, 'final_amount' => 50_000]);
    $destination = Balance::factory()->for($user)->create(['name' => 'Bank', 'initial_amount' => 100_000, 'final_amount' => 100_000]);

    TransferBetweenAccounts::run(new TransferBetweenAccountsData(
        from_account_id: $source->id,
        to_account_id: $destination->id,
        date: CarbonImmutable::now(),
        amount: 200_000,
        description: 'overdraft',
    ));
})->throws(ValidationException::class, 'Insufficient balance');

test('deleting one transfer leg deletes the pair and resyncs both balances', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $source = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 500_000, 'final_amount' => 500_000]);
    $destination = Balance::factory()->for($user)->create(['name' => 'Bank', 'initial_amount' => 100_000, 'final_amount' => 100_000]);

    TransferBetweenAccounts::run(new TransferBetweenAccountsData(
        from_account_id: $source->id,
        to_account_id: $destination->id,
        date: CarbonImmutable::now(),
        amount: 200_000,
        description: 'split',
    ));

    $leg = Transaction::where('transfer_group_id', '!=', null)->first();
    DeleteTransaction::run($leg);

    expect(Transaction::where('transfer_group_id', '!=', null)->count())->toBe(0)
        ->and($source->fresh()->final_amount)->toBe(500_000)
        ->and($destination->fresh()->final_amount)->toBe(100_000);
});

test('regular transaction delete only removes itself and resyncs the balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->for($user)->create();
    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 500_000, 'final_amount' => 490_000]);
    $txn = Transaction::factory()->for($user)->for($balance)->for($category)->create(['amount' => 10_000, 'type' => CategoryType::EXPENSE]);

    DeleteTransaction::run($txn);

    expect(Transaction::count())->toBe(0)
        ->and($balance->fresh()->final_amount)->toBe(500_000);
});

test('save budget prunes removed items but keeps transaction-referenced rows', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $categoryA = Category::factory()->for($user)->create(['name' => 'Food']);
    $categoryB = Category::factory()->for($user)->create(['name' => 'Travel']);
    $budget = Budget::factory()->for($user)->create(['cutoff_day' => 1]);

    $itemA = $budget->items()->create(['category_id' => $categoryA->id, 'type' => CategoryType::EXPENSE, 'planned_amount' => 100_000]);
    $itemB = $budget->items()->create(['category_id' => $categoryB->id, 'type' => CategoryType::EXPENSE, 'planned_amount' => 200_000]);

    // itemB gets a transaction reference so it must survive pruning.
    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 500_000, 'final_amount' => 450_000]);
    Transaction::factory()->for($user)->for($balance)->for($categoryB)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 50_000,
        'budget_item_id' => $itemB->id,
        'budget_id' => $budget->id,
    ]);

    SaveBudget::run($budget, BudgetData::from([
        'period_start' => $budget->period_start->toDateString(),
        'period_end' => $budget->period_end->toDateString(),
        'cutoff_day' => $budget->cutoff_day,
        'notes' => null,
        'items' => [
            ['id' => $itemA->id, 'category_id' => $categoryA->id, 'type' => CategoryType::EXPENSE, 'planned_amount' => 150_000],
        ],
    ]));

    $budget->refresh();
    expect($budget->items()->pluck('category_id')->sort()->values()->all())->toBe([$categoryA->id, $categoryB->id])
        ->and($budget->items()->find($itemA->id)->planned_amount)->toBe(150_000);
});
