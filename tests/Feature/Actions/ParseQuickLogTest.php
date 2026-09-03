<?php

use App\Actions\ParseQuickLog;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function quickUser(): User
{
    return User::factory()->create();
}

it('parses note + amount with k multiplier and balance hint', function () {
    $user = quickUser();
    $cash = Balance::factory()->for($user)->create(['name' => 'Cash']);
    $bca = Balance::factory()->for($user)->create(['name' => 'BCA']);
    $trans = Category::factory()->for($user)->create(['name' => 'Transportation', 'type' => CategoryType::EXPENSE]);
    Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);

    $res = ParseQuickLog::run(
        'bensin 33k cash',
        $user->categories()->get(),
        $user->balances()->get(),
        null,
        null,
        null,
    );

    expect($res['amount'])->toBe(33_000)
        ->and($res['category_id'])->toBe($trans->id)
        ->and($res['balance_id'])->toBe($cash->id)
        ->and($res['note'])->toBe('bensin');
});

it('resolves k / rb / ribu and plain amounts', function () {
    $user = quickUser();
    $b = Balance::factory()->for($user)->create(['name' => 'Cash']);
    // at least one category so parse does not early-exit
    Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);
    $cats = $user->categories()->get();
    $bals = collect([$b]);

    $cases = [
        ['makan 2.5k', 2_500],
        ['makan 33rb', 33_000],
        ['makan 10ribu', 10_000],
        ['makan 15000', 15_000],
        ['makan 15,000', 15_000],
    ];
    foreach ($cases as [$line, $expected]) {
        $res = ParseQuickLog::run($line, $cats, $bals, null, null, null);
        expect($res['amount'])->toBe($expected);
    }
});

it('resolves amount token in middle (bensin 33k cash) not only trailing', function () {
    $user = quickUser();
    $cash = Balance::factory()->for($user)->create(['name' => 'Cash']);
    Category::factory()->for($user)->create(['name' => 'Transportation', 'type' => CategoryType::EXPENSE]);
    $res = ParseQuickLog::run('bensin 33k cash', $user->categories()->get(), collect([$cash]), null, null, null);
    expect($res['amount'])->toBe(33_000)
        ->and($res['note'])->toBe('bensin');
});

it('falls back to last-used on ambiguous category', function () {
    // Two categories whose names collide on the needle with NO exact match -> ambiguous.
    // Using Food Stall / Food Court so "food" is a substring of both, not an exact hit.
    $user = quickUser();
    $b = Balance::factory()->for($user)->create(['name' => 'Cash']);
    $stall = Category::factory()->for($user)->create(['name' => 'Food Stall', 'type' => CategoryType::EXPENSE]);
    Category::factory()->for($user)->create(['name' => 'Food Court', 'type' => CategoryType::EXPENSE]);
    // substring-ambiguous needle "food" should fall back to last-used
    $res = ParseQuickLog::run('food 10k', $user->categories()->get(), collect([$b]), $stall->id, null, null);
    expect($res['category_id'])->toBe($stall->id);

    // without a last-used fallback, ambiguous yields null (do not invent)
    $res2 = ParseQuickLog::run('food 10k', $user->categories()->get(), collect([$b]), null, null, null);
    expect($res2['category_id'])->toBeNull();
});

it('resolves balance from tail, else last-used, else primary', function () {
    $user = quickUser();
    $cash = Balance::factory()->for($user)->create(['name' => 'Cash']);
    $bca = Balance::factory()->for($user)->create(['name' => 'BCA']);
    Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);
    $cats = $user->categories()->get();

    $res = ParseQuickLog::run('makan 10k cash', $cats, collect([$cash, $bca]), $bca->id, null, null);
    expect($res['balance_id'])->toBe($cash->id);

    $res2 = ParseQuickLog::run('makan 10k', $cats, collect([$cash, $bca]), null, $bca->id, null);
    expect($res2['balance_id'])->toBe($bca->id);

    $res3 = ParseQuickLog::run('makan 10k', $cats, collect([$cash, $bca]), null, null, $cash->id);
    expect($res3['balance_id'])->toBe($cash->id);
});

it('does not invent categories', function () {
    $user = quickUser();
    $b = Balance::factory()->for($user)->create(['name' => 'Cash']);
    Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);
    $res = ParseQuickLog::run('unknownxyz 10k', $user->categories()->get(), collect([$b]), null, null, null);
    expect($res['category_id'])->toBeNull();
});
