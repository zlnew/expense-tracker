<?php

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
});

test('balances index renders balance list with formatted accounts', function () {
    $response = $this->get(route('balances.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BalanceList')
            ->has('balances.data')
        );
});

test('balances show renders balance detail with paginated transactions', function () {
    $category = Category::factory()->create(['user_id' => $this->user->id]);
    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $category->id,
        'amount' => 50000,
    ]);

    $response = $this->get(route('balances.show', $this->balance));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BalanceDetail')
            ->has('balance')
            ->has('transactions.data', 1)
        );
});

test('balance can be created', function () {
    $response = $this->post(route('balances.store'), [
        'name' => 'BCA Savings',
        'description' => 'Checking account',
        'initial_amount' => 2500000,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('balances', [
        'user_id' => $this->user->id,
        'name' => 'BCA Savings',
        'initial_amount' => 2500000,
        'final_amount' => 2500000,
    ]);
});

test('balance can be updated', function () {
    $response = $this->put(route('balances.update', $this->balance), [
        'name' => 'Renamed Account',
        'description' => 'Updated desc',
        'initial_amount' => 1000000,
    ]);

    $response->assertRedirect();
    expect($this->balance->fresh()->name)->toBe('Renamed Account');
});

test('balance can be set as primary', function () {
    $secondBalance = Balance::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Secondary Account',
        'is_primary' => false,
    ]);

    $response = $this->post(route('balances.set-primary', $secondBalance));

    $response->assertRedirect();
    expect($secondBalance->fresh()->is_primary)->toBeTrue()
        ->and($this->balance->fresh()->is_primary)->toBeFalse();
});

test('balance can be reconciled with drift calculation', function () {
    $response = $this->post(route('balances.reconcile', $this->balance), [
        'reconciled_amount' => 950000,
        'reconciled_at' => now()->toDateString(),
    ]);

    $response->assertRedirect();
    $fresh = $this->balance->fresh();
    expect($fresh->reconciled_amount)->toBe(950000)
        ->and($fresh->drift)->toBe(50000);
});

test('balance can be deleted', function () {
    $balanceToDelete = Balance::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'To Delete',
        'is_primary' => false,
    ]);

    $response = $this->delete(route('balances.destroy', $balanceToDelete));

    $response->assertRedirect();
    $this->assertSoftDeleted('balances', ['id' => $balanceToDelete->id]);
});

test('ownership middleware blocks unauthorized access to another users balance', function () {
    $otherUser = User::factory()->create();
    $otherBalance = Balance::factory()->create(['user_id' => $otherUser->id]);

    $this->get(route('balances.show', $otherBalance))->assertForbidden();
    $this->put(route('balances.update', $otherBalance), ['name' => 'Hacked', 'amount' => 100])->assertForbidden();
    $this->post(route('balances.set-primary', $otherBalance))->assertForbidden();
    $this->post(route('balances.reconcile', $otherBalance), ['reconciled_amount' => 100, 'reconciled_at' => now()->toDateString()])->assertForbidden();
    $this->delete(route('balances.destroy', $otherBalance))->assertForbidden();
});
