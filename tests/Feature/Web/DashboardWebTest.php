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
});

test('unauthenticated user is redirected to login from dashboard', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated user loads dashboard with core summary and impending drains', function () {
    $this->actingAs($this->user);

    $balance = Balance::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Cash',
        'initial_amount' => 1000000,
        'final_amount' => 1000000,
        'is_primary' => true,
    ]);

    $category = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Food',
    ]);

    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $balance->id,
        'category_id' => $category->id,
        'amount' => 25000,
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('summary_cards')
            ->has('budget_progress')
            ->has('recent_transactions')
            ->has('impending_drains')
        );
});

test('dashboard impending-drains endpoint returns windowed cash outflow projection', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('dashboard.impending-drains', ['window' => 30]));

    $response->assertOk()
        ->assertJsonStructure([
            'window_days',
            'from',
            'until',
            'total_impending_outflow',
            'items',
            'per_balance',
            'has_negative_warning',
        ]);
});
