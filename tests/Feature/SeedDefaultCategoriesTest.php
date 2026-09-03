<?php

use App\Actions\SeedDefaultCategories;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seed default categories creates all default income and expense categories for new user', function () {
    $user = User::factory()->create();

    expect($user->categories()->count())->toBe(0);

    SeedDefaultCategories::run($user);

    $defaultCount = count(DefaultCategories::all());
    expect($user->categories()->count())->toBe($defaultCount);

    $categoryNames = $user->categories()->pluck('name')->all();
    expect($categoryNames)->toContain('Food')
        ->toContain('Paycheck');
});

test('seed default categories is idempotent and does not create duplicates', function () {
    $user = User::factory()->create();

    SeedDefaultCategories::run($user);
    $countAfterFirst = $user->categories()->count();

    // Re-run
    SeedDefaultCategories::run($user);
    $countAfterSecond = $user->categories()->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});
