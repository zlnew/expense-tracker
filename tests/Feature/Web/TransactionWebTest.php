<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->balance = Balance::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Main Account',
        'initial_amount' => 1000000,
        'final_amount' => 1000000,
        'is_primary' => true,
    ]);

    $this->category = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Food & Dining',
        'type' => CategoryType::EXPENSE,
    ]);
});

test('transactions index renders transaction list with filter options', function () {
    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'amount' => 50000,
        'description' => 'Lunch',
    ]);

    $response = $this->get(route('transactions.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TransactionList')
            ->has('transactions.data', 1)
            ->has('balances')
            ->has('categories')
        );
});

test('transactions export streams valid csv file', function () {
    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'amount' => 75000,
        'description' => 'Dinner with team',
        'date' => now()->toDateString(),
    ]);

    $response = $this->get(route('transactions.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('transaction can be stored and balance is adjusted', function () {
    $response = $this->post(route('transactions.store'), [
        'amount' => 150000,
        'type' => 'expense',
        'date' => now()->toDateString(),
        'category_id' => $this->category->id,
        'balance_id' => $this->balance->id,
        'description' => 'Weekly Groceries',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'amount' => 150000,
        'description' => 'Weekly Groceries',
    ]);

    expect($this->balance->fresh()->final_amount)->toBe(850000);
});

test('transaction can be updated and balance is resynced', function () {
    $txn = Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'amount' => 100000,
        'type' => CategoryType::EXPENSE,
    ]);
    $this->balance->update(['final_amount' => 900000]);

    $response = $this->put(route('transactions.update', $txn), [
        'amount' => 200000,
        'type' => 'expense',
        'date' => now()->toDateString(),
        'category_id' => $this->category->id,
        'balance_id' => $this->balance->id,
        'description' => 'Updated amount',
    ]);

    $response->assertRedirect();
    expect($this->balance->fresh()->final_amount)->toBe(800000);
});

test('transaction can be deleted and balance is reverted', function () {
    $txn = Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'amount' => 100000,
        'type' => CategoryType::EXPENSE,
    ]);
    $this->balance->update(['final_amount' => 900000]);

    $response = $this->delete(route('transactions.destroy', $txn));

    $response->assertRedirect();
    $this->assertSoftDeleted('transactions', ['id' => $txn->id]);
    expect($this->balance->fresh()->final_amount)->toBe(1000000);
});

test('transfer between accounts moves funds between accounts', function () {
    $destBalance = Balance::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Bank Mandiri',
        'initial_amount' => 500000,
        'final_amount' => 500000,
    ]);

    $response = $this->post(route('transactions.transfer-between-accounts'), [
        'from_account_id' => $this->balance->id,
        'to_account_id' => $destBalance->id,
        'amount' => 200000,
        'date' => now()->toDateString(),
        'description' => 'ATM Transfer',
    ]);

    $response->assertRedirect();
    expect($this->balance->fresh()->final_amount)->toBe(800000)
        ->and($destBalance->fresh()->final_amount)->toBe(700000);
});

test('ownership middleware prevents unauthorized modification of another users transaction', function () {
    $otherUser = User::factory()->create();
    $otherTxn = Transaction::factory()->create([
        'user_id' => $otherUser->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
    ]);

    $this->put(route('transactions.update', $otherTxn), [
        'amount' => 100,
        'type' => 'expense',
        'date' => now()->toDateString(),
        'category_id' => $this->category->id,
        'balance_id' => $this->balance->id,
    ])->assertForbidden();

    $this->delete(route('transactions.destroy', $otherTxn))->assertForbidden();
});
