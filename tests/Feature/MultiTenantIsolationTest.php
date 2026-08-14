<?php

use App\Actions\Fortify\CreateNewUser;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // These tests exercise authorization, not CSRF protection.
    $this->withoutMiddleware(PreventRequestForgery::class);
});

test('user cannot see another user transactions in list', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $category = Category::factory()->for($owner)->create();
    $balance = Balance::factory()->for($owner)->create(['name' => 'Owner Cash', 'initial_amount' => 100_000, 'final_amount' => 90_000]);
    Transaction::factory()->for($owner)->for($balance)->for($category)->create([
        'amount' => 10_000,
        'type' => CategoryType::EXPENSE,
        'description' => 'secret',
    ]);

    $this->actingAs($intruder)->get(route('transactions.index'))
        ->assertOk()
        ->assertDontSee('secret');
});

test('user cannot update or delete another user transaction', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $category = Category::factory()->for($owner)->create();
    $balance = Balance::factory()->for($owner)->create(['name' => 'Owner Cash', 'initial_amount' => 100_000, 'final_amount' => 90_000]);
    $txn = Transaction::factory()->for($owner)->for($balance)->for($category)->create([
        'amount' => 10_000,
        'type' => CategoryType::EXPENSE,
    ]);

    $this->actingAs($intruder)
        ->put(route('transactions.update', $txn), [
            'balance_id' => $balance->id,
            'type' => CategoryType::EXPENSE->value,
            'date' => now()->toDateString(),
            'amount' => 5_000,
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('transactions.destroy', $txn))
        ->assertForbidden();

    expect($txn->fresh()->amount)->toBe(10_000);
});

test('user cannot touch another user balance', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $balance = Balance::factory()->for($owner)->create(['name' => 'Owner Wallet', 'initial_amount' => 100_000, 'final_amount' => 100_000]);

    $this->actingAs($intruder)
        ->put(route('balances.update', $balance), ['name' => 'Hijacked', 'description' => null, 'initial_amount' => 1, 'final_amount' => 1, 'is_primary' => false])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('balances.destroy', $balance))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->post(route('balances.set-primary', $balance))
        ->assertForbidden();

    expect($balance->fresh()->name)->toBe('Owner Wallet');
});

test('user cannot touch another user budget', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $budget = Budget::factory()->for($owner)->create(['cutoff_day' => 1]);

    $this->actingAs($intruder)
        ->put(route('budgets.update', $budget), [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'cutoff_day' => 1,
            'notes' => null,
            'items' => [],
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('budgets.destroy', $budget))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->post(route('budgets.set-active', $budget))
        ->assertForbidden();
});

test('user cannot update or delete another user category', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $category = Category::factory()->for($owner)->create(['name' => 'Private', 'type' => CategoryType::EXPENSE]);

    $this->actingAs($intruder)
        ->put(route('categories.update', $category), ['name' => 'Hijacked', 'type' => CategoryType::EXPENSE->value])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('categories.destroy', $category))
        ->assertForbidden();

    expect($category->fresh()->name)->toBe('Private');
});

test('transfer cannot move funds between another user accounts', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $ownerSource = Balance::factory()->for($owner)->create(['name' => 'Owner Cash', 'initial_amount' => 500_000, 'final_amount' => 500_000]);
    $intruderDest = Balance::factory()->for($intruder)->create(['name' => 'Intruder Bank', 'initial_amount' => 0, 'final_amount' => 0]);

    // The request validation scopes account ids to the authenticated user, so
    // submitting the owner's balance as source must be rejected (422) and no
    // funds may move.
    $this->actingAs($intruder)
        ->post(route('transactions.transfer-between-accounts'), [
            'from_account_id' => $ownerSource->id,
            'to_account_id' => $intruderDest->id,
            'date' => now()->toDateString(),
            'amount' => 100_000,
            'description' => null,
        ])
        ->assertSessionHasErrors('from_account_id');

    expect($ownerSource->fresh()->final_amount)->toBe(500_000)
        ->and($intruderDest->fresh()->final_amount)->toBe(0);
});

test('registering a user seeds the default categories', function () {
    $user = (new CreateNewUser)->create([
        'name' => 'Fresh',
        'email' => 'fresh@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect($user->categories()->count())->toBe(16)
        ->and($user->categories()->where('name', 'Food')->exists())->toBeTrue()
        ->and($user->categories()->where('name', 'Maintenance')->exists())->toBeTrue()
        ->and($user->categories()->where('name', 'Taxes')->exists())->toBeTrue();
});
