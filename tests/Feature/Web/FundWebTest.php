<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\FundContribution;
use App\Models\SinkingFund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->balance = Balance::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Main Checking',
        'initial_amount' => 5000000,
        'final_amount' => 5000000,
    ]);

    $this->category = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Vehicle Maintenance',
        'type' => CategoryType::EXPENSE,
    ]);

    $this->fund = SinkingFund::factory()->create([
        'user_id' => $this->user->id,
        'from_balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'name' => 'Car Service Fund',
        'target_amount' => 1200000,
        'cadence' => 'monthly',
        'due_interval_months' => 1,
        'next_due' => now()->addMonth()->toDateString(),
    ]);
});

test('funds index renders funds list with progress metadata', function () {
    $response = $this->get(route('funds.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FundsList')
            ->has('funds', 1)
            ->has('categories')
            ->has('balances')
        );
});

test('sinking fund can be created', function () {
    $response = $this->post(route('funds.store'), [
        'name' => 'Home Renovation',
        'target_amount' => 10000000,
        'cadence' => 'monthly',
        'category_id' => $this->category->id,
        'from_balance_id' => $this->balance->id,
        'due_interval_months' => 6,
        'next_due' => now()->addMonths(6)->toDateString(),
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('sinking_funds', [
        'user_id' => $this->user->id,
        'name' => 'Home Renovation',
        'target_amount' => 10000000,
    ]);
});

test('sinking fund can be updated', function () {
    $response = $this->put(route('funds.update', $this->fund), [
        'name' => 'Updated Car Service Fund',
        'target_amount' => 1500000,
        'cadence' => 'monthly',
        'category_id' => $this->category->id,
        'from_balance_id' => $this->balance->id,
        'due_interval_months' => 2,
    ]);

    $response->assertRedirect();
    expect($this->fund->fresh()->name)->toBe('Updated Car Service Fund');
});

test('contribution can be recorded for sinking fund', function () {
    $response = $this->post(route('funds.contributions.store', $this->fund), [
        'amount' => 300000,
        'date' => now()->toDateString(),
        'description' => 'First monthly set-aside',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('fund_contributions', [
        'fund_id' => $this->fund->id,
        'type' => 'contribution',
        'amount' => 300000,
    ]);
});

test('withdrawal payout can be recorded for sinking fund', function () {
    // Contribute first so we have reserve
    $this->post(route('funds.contributions.store', $this->fund), [
        'amount' => 500000,
        'date' => now()->toDateString(),
    ]);

    $response = $this->post(route('funds.withdrawals.store', $this->fund), [
        'amount' => 250000,
        'date' => now()->toDateString(),
        'description' => 'Oil change payout',
        'balance_id' => $this->balance->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('fund_contributions', [
        'fund_id' => $this->fund->id,
        'type' => 'withdrawal',
        'amount' => 250000,
    ]);
});

test('fund contribution can be deleted', function () {
    $contribution = FundContribution::factory()->create([
        'fund_id' => $this->fund->id,
        'user_id' => $this->user->id,
        'type' => 'contribution',
        'amount' => 100000,
        'date' => now()->toDateString(),
    ]);

    $response = $this->delete(route('fund-contributions.destroy', $contribution));

    $response->assertRedirect();
    $this->assertDatabaseMissing('fund_contributions', ['id' => $contribution->id]);
});

test('sinking fund can be deleted', function () {
    $response = $this->delete(route('funds.destroy', $this->fund));

    $response->assertRedirect();
    $this->assertSoftDeleted('sinking_funds', ['id' => $this->fund->id]);
});

test('ownership middleware blocks unauthorized fund access', function () {
    $otherUser = User::factory()->create();
    $otherFund = SinkingFund::factory()->create([
        'user_id' => $otherUser->id,
        'from_balance_id' => $this->balance->id,
    ]);

    $this->put(route('funds.update', $otherFund), ['name' => 'Hacked'])->assertForbidden();
    $this->post(route('funds.contributions.store', $otherFund), ['amount' => 100, 'date' => now()->toDateString()])->assertForbidden();
    $this->delete(route('funds.destroy', $otherFund))->assertForbidden();
});
