<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('exports transactions as CSV honoring filters', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food']);

    Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 25_000,
        'description' => 'Lunch',
        'date' => now()->startOfMonth(),
    ]);

    Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::INCOME,
        'amount' => 1_000_000,
        'description' => 'Salary',
        'date' => now()->startOfMonth(),
    ]);

    // Second user's transaction must not leak into the export.
    $other = User::factory()->create();
    $otherBalance = Balance::factory()->for($other)->create(['name' => 'Other', 'initial_amount' => 1, 'final_amount' => 1]);
    Transaction::factory()->for($other)->for($otherBalance)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 999_999,
        'description' => 'Secret',
        'date' => now(),
    ]);

    $response = $this->get(route('transactions.export'))
        ->assertOk()
        ->assertDownload('transactions-'.now()->format('Y-m-d-His').'.csv');

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->toContain('Date,Type,Category,Balance,Amount,Description')
        ->toContain('Lunch')
        ->toContain('Salary')
        ->not->toContain('Secret');
});

test('export applies date range filter', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);

    Transaction::factory()->for($user)->for($balance)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 10_000,
        'description' => 'January expense',
        'date' => '2026-01-15',
    ]);

    Transaction::factory()->for($user)->for($balance)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 20_000,
        'description' => 'February expense',
        'date' => '2026-02-15',
    ]);

    $response = $this->get(route('transactions.export', ['dateFrom' => '2026-02-01']))
        ->assertOk();

    $content = $response->streamedContent();

    expect($content)->toContain('February expense')
        ->not->toContain('January expense');
});
