<?php

use App\Actions\GetImpendingDrains;
use App\Actions\SaveFund;
use App\DTO\FundData;
use App\Models\Balance;
use App\Models\RecurringTransaction;
use App\Models\SinkingFund;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makeBalance(User $user, int $finalAmount = 1_000_000, bool $primary = false): Balance
{
    return Balance::factory()->for($user)->create([
        'initial_amount' => $finalAmount,
        'final_amount' => $finalAmount,
        'is_primary' => $primary,
    ]);
}

test('combines fund dues and recurring within the window and totals correctly', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-08-10');
    CarbonImmutable::setTestNow($today);

    $b1 = makeBalance($user, 2_000_000, true);
    $b2 = makeBalance($user, 500_000);

    $fundDueSoon = SinkingFund::factory()->for($user)->create([
        'name' => 'Laptop',
        'target_amount' => 1_200_000,
        'contribution_amount' => 100_000,
        'from_balance_id' => $b1->id,
        'next_due' => $today->addDays(5)->toDateString(),
    ]);
    $fundFar = SinkingFund::factory()->for($user)->create([
        'name' => 'Far fund',
        'target_amount' => 1_200_000,
        'contribution_amount' => 999_999,
        'from_balance_id' => $b1->id,
        'next_due' => $today->addDays(90)->toDateString(),
    ]);
    $recurringSoon = RecurringTransaction::factory()->for($user)->for($b2)->create([
        'amount' => 75_000,
        'next_run_date' => $today->addDays(10)->toDateString(),
        'is_active' => true,
    ]);
    RecurringTransaction::factory()->for($user)->for($b2)->create([
        'amount' => 123_456,
        'next_run_date' => $today->addDays(80)->toDateString(),
        'is_active' => true,
    ]);

    $result = GetImpendingDrains::run($user->id, 60, $today);

    expect($result['window_days'])->toBe(60);
    expect($result['from'])->toBe($today->toDateString());
    // Only the soon fund + soon recurring should appear (60d window)
    $ids = collect($result['items'])->pluck('id')->all();
    expect($ids)->toContain($fundDueSoon->id);
    expect($ids)->not->toContain($fundFar->id);
    expect($result['items'])->toHaveCount(2);
    expect($result['total_impending_outflow'])->toBe(100_000 + 75_000);

    $byBalance = collect($result['per_balance'])->keyBy('balance_id');
    expect((int) $byBalance[$b1->id]['impending'])->toBe(100_000);
    expect((int) $byBalance[$b2->id]['impending'])->toBe(75_000);
    // Real = final_amount (no contributions yet), projected = Real - impending
    expect((int) $byBalance[$b1->id]['projected_free_after'])->toBe(2_000_000 - 100_000);
    expect($result['has_negative_warning'])->toBeFalse();

    CarbonImmutable::setTestNow();
});

test('window filters horizon and includes overdue fund dues', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-08-10');
    CarbonImmutable::setTestNow($today);
    $b = makeBalance($user, 1_000_000, true);

    $overdue = SinkingFund::factory()->for($user)->create([
        'contribution_amount' => 50_000,
        'from_balance_id' => $b->id,
        'next_due' => $today->subDays(3)->toDateString(),
    ]);
    $future = SinkingFund::factory()->for($user)->create([
        'contribution_amount' => 50_000,
        'from_balance_id' => $b->id,
        'next_due' => $today->addDays(40)->toDateString(),
    ]);

    $narrow = GetImpendingDrains::run($user->id, 1, $today);
    expect(collect($narrow['items'])->pluck('id')->all())->toContain($overdue->id);
    expect(collect($narrow['items'])->pluck('id')->all())->not->toContain($future->id);

    $wide = GetImpendingDrains::run($user->id, 60, $today);
    expect(collect($wide['items'])->pluck('id')->all())->toContain($future->id);

    CarbonImmutable::setTestNow();
});

test('warns when impending would push a balance negative', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-08-10');
    CarbonImmutable::setTestNow($today);

    $b = makeBalance($user, 40_000, true);
    SinkingFund::factory()->for($user)->create([
        'contribution_amount' => 60_000,
        'from_balance_id' => $b->id,
        'next_due' => $today->addDays(2)->toDateString(),
    ]);

    $result = GetImpendingDrains::run($user->id, 60, $today);
    expect($result['has_negative_warning'])->toBeTrue();
    $row = collect($result['per_balance'])->firstWhere('balance_id', $b->id);
    expect($row['would_go_negative'])->toBeTrue();
    expect($row['projected_free_after'])->toBe(-20_000);

    CarbonImmutable::setTestNow();
});

test('inactive recurrings are excluded and API scopes to authenticated user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $today = CarbonImmutable::parse('2026-08-10');
    CarbonImmutable::setTestNow($today);

    $b = makeBalance($user, 1_000_000, true);
    $ob = makeBalance($other, 1_000_000, true);

    RecurringTransaction::factory()->for($user)->for($b)->create([
        'amount' => 10_000,
        'next_run_date' => $today->addDays(5)->toDateString(),
        'is_active' => false,
    ]);
    $active = RecurringTransaction::factory()->for($user)->for($b)->create([
        'amount' => 11_000,
        'next_run_date' => $today->addDays(5)->toDateString(),
        'is_active' => true,
    ]);
    SinkingFund::factory()->for($other)->create([
        'contribution_amount' => 99_000,
        'from_balance_id' => $ob->id,
        'next_due' => $today->addDays(5)->toDateString(),
    ]);

    $result = GetImpendingDrains::run($user->id, 60, $today);
    expect(collect($result['items'])->pluck('id')->all())->toContain($active->id);

    // API: only scoped to the authenticated user, scoped by balances:read
    Sanctum::actingAs($user, ['balances:read']);
    $this->getJson('/api/impending-drains?window=60')->assertOk()
        ->assertJsonPath('total_impending_outflow', 11_000);

    Sanctum::actingAs($other, ['balances:read']);
    $this->getJson('/api/impending-drains?window=60')->assertOk()
        ->assertJsonPath('total_impending_outflow', 99_000);

    CarbonImmutable::setTestNow();
});

test('window clamps and unauthorized/api ability checks for impending-drains', function () {
    $user = User::factory()->create();

    $this->getJson('/api/impending-drains')->assertUnauthorized();

    Sanctum::actingAs($user, ['balances:write']);
    $this->getJson('/api/impending-drains')->assertForbidden();

    Sanctum::actingAs($user, ['balances:read']);
    $this->getJson('/api/impending-drains?window=9999')->assertOk()
        ->assertJsonPath('window_days', 365);
    $this->getJson('/api/impending-drains?window=0')->assertOk()
        ->assertJsonPath('window_days', 1);
});
