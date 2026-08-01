<?php

use App\Actions\ProcessRecurringTransactions;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('due recurring creates the transaction and advances next run', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);

    $recurring = RecurringTransaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 150_000,
        'frequency' => 'monthly',
        'next_run_date' => now()->toDateString(),
    ]);

    $count = ProcessRecurringTransactions::run();

    expect($count)->toBe(1)
        ->and(Transaction::count())->toBe(1)
        ->and(Transaction::first()->amount)->toBe(150_000)
        ->and(Transaction::first()->user_id)->toBe($user->id)
        ->and($recurring->fresh()->next_run_date->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
});

test('not-yet-due recurring is not processed', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);

    RecurringTransaction::factory()->for($user)->for($balance)->create([
        'next_run_date' => now()->addDay()->toDateString(),
    ]);

    $count = ProcessRecurringTransactions::run();

    expect($count)->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

test('recurring past its end date is deactivated after processing', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);

    $recurring = RecurringTransaction::factory()->for($user)->for($balance)->create([
        'frequency' => 'weekly',
        'next_run_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    ProcessRecurringTransactions::run();

    expect($recurring->fresh()->is_active)->toBeFalse()
        ->and(Transaction::count())->toBe(1);
});

test('recurring page is scoped per user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $balance = Balance::factory()->for($owner)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    RecurringTransaction::factory()->for($owner)->for($balance)->create(['description' => 'secret-schedule']);

    $this->withoutMiddleware(PreventRequestForgery::class);

    $this->actingAs($intruder)
        ->get(route('recurring-transactions.index'))
        ->assertOk()
        ->assertDontSee('secret-schedule');

    $this->actingAs($owner)
        ->get(route('recurring-transactions.index'))
        ->assertOk()
        ->assertSee('secret-schedule');
});

test('user cannot update or delete another user recurring', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $balance = Balance::factory()->for($owner)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $recurring = RecurringTransaction::factory()->for($owner)->for($balance)->create(['description' => 'mine']);

    $this->withoutMiddleware(PreventRequestForgery::class);

    $this->actingAs($intruder)
        ->put(route('recurring-transactions.update', $recurring), [
            'type' => CategoryType::EXPENSE->value,
            'balance_id' => $balance->id,
            'amount' => 100_000,
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'next_run_date' => now()->toDateString(),
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('recurring-transactions.destroy', $recurring))
        ->assertForbidden();

    expect($recurring->fresh()->description)->toBe('mine');
});
