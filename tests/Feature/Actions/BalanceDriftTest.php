<?php

use App\Actions\SaveTransaction;
use App\DTO\TransactionData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * US-4 balance-drift coverage (t_dd9cb24b FLAG-1).
 *
 * Drift model: final_amount − reconciled_amount, computed by the Balance
 * accessors and surfaced through BalanceData (drift / is_drift_flagged are
 * appended attributes). A card is flagged only outside ±Rp 500 tolerance
 * (Balance::DRIFT_TOLERANCE). Reconciling happens over two authenticated
 * surfaces that must agree:
 *   - web  POST /balances/{balance}/reconcile   (session auth + ownership)
 *   - api  POST /api/balances/{balance}/reconcile (Sanctum, balances:write)
 */
test('drift and flag stay null-safe before any reconciliation', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'initial_amount' => 1_000_000,
        'final_amount' => 1_000_000,
    ]);

    expect($balance->reconciled_amount)->toBeNull();
    expect($balance->drift)->toBeNull();
    expect($balance->is_drift_flagged)->toBeFalse();
});

test('drift flag respects the ±500 tolerance boundary', function () {
    $user = User::factory()->create();

    // |drift| == tolerance stays green, one rupiah past it goes red.
    $atTolerance = Balance::factory()->for($user)->create(['final_amount' => 100_000]);
    $atTolerance->reconciled_amount = 99_500;
    expect($atTolerance->drift)->toBe(500);
    expect($atTolerance->is_drift_flagged)->toBeFalse();

    $pastTolerance = Balance::factory()->for($user)->create(['final_amount' => 100_000]);
    $pastTolerance->reconciled_amount = 100_501;
    expect($pastTolerance->drift)->toBe(-501);
    expect($pastTolerance->is_drift_flagged)->toBeTrue();

    // Sign doesn't matter — both directions of shrinkage/growth are drift.
    $positiveDrift = Balance::factory()->for($user)->create(['final_amount' => 200_000]);
    $positiveDrift->reconciled_amount = 199_000;
    expect($positiveDrift->drift)->toBe(1_000);
    expect($positiveDrift->is_drift_flagged)->toBeTrue();
});

test('web reconcile persists the count and flashes success', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['final_amount' => 750_000]);

    $this->actingAs($user)
        ->from('/balances')
        ->post("/balances/{$balance->id}/reconcile", [
            'reconciled_amount' => 749_000,
            'reconciled_at' => '2026-08-25',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $balance->refresh();
    expect($balance->reconciled_amount)->toBe(749_000);
    expect((string) $balance->reconciled_at->toDateString())->toBe('2026-08-25');
    expect($balance->is_drift_flagged)->toBeTrue();
});

test('web reconcile refuses balances owned by another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $balance = Balance::factory()->for($owner)->create(['final_amount' => 100_000]);

    $this->actingAs($intruder)
        ->post("/balances/{$balance->id}/reconcile", [
            'reconciled_amount' => 90_000,
            'reconciled_at' => '2026-08-25',
        ])
        ->assertForbidden();

    expect($balance->fresh()->reconciled_amount)->toBeNull();
});

test('web reconcile validates amount and date', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post("/balances/{$balance->id}/reconcile", [
            'reconciled_at' => 'not-a-date',
        ])
        ->assertSessionHasErrors(['reconciled_amount', 'reconciled_at']);

    expect($balance->fresh()->reconciled_amount)->toBeNull();
});

test('api reconcile stores the reconciliation and echoes the drift surface', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['final_amount' => 2_000_000]);

    Sanctum::actingAs($user, ['balances:write']);
    $res = $this->postJson("/api/balances/{$balance->id}/reconcile", [
        'reconciled_amount' => 1_999_000,
        'reconciled_at' => '2026-08-26',
    ])->assertOk();

    // The response carries the full drift surface the UI renders from.
    expect($res->json('reconciled_amount'))->toBe(1_999_000);
    expect($res->json('drift'))->toBe(1_000);
    expect($res->json('is_drift_flagged'))->toBeTrue();

    $balance->refresh();
    expect($balance->reconciled_amount)->toBe(1_999_000);
});

test('api reconcile requires the balances write ability', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create();

    Sanctum::actingAs($user, ['balances:read']);
    $this->postJson("/api/balances/{$balance->id}/reconcile", [
        'reconciled_amount' => 1_000,
        'reconciled_at' => '2026-08-26',
    ])->assertForbidden();

    // A valid token without balances:write is forbidden, not unauthorized.
    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/balances/{$balance->id}/reconcile", [
        'reconciled_amount' => 1_000,
        'reconciled_at' => '2026-08-26',
    ])->assertForbidden();

    expect($balance->fresh()->reconciled_amount)->toBeNull();
});

test('api reconcile is scoped to the owning user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $balance = Balance::factory()->for($owner)->create();

    Sanctum::actingAs($intruder, ['balances:read', 'balances:write']);
    $this->postJson("/api/balances/{$balance->id}/reconcile", [
        'reconciled_amount' => 1_000,
        'reconciled_at' => '2026-08-26',
    ])->assertNotFound();

    expect($balance->fresh()->reconciled_amount)->toBeNull();
});

test('api reconcile validates amount and date', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create();

    Sanctum::actingAs($user, ['balances:write']);
    $this->postJson("/api/balances/{$balance->id}/reconcile", [
        'reconciled_amount' => '12.5',
        'reconciled_at' => 'tomorrow-ish',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['reconciled_amount', 'reconciled_at']);

    expect($balance->fresh()->reconciled_amount)->toBeNull();
});

test('a drifted balance clears its flag after reconciling to the real amount', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['final_amount' => 300_000]);

    Sanctum::actingAs($user, ['balances:read', 'balances:write']);

    // First count disagrees well beyond tolerance → list flags the card red.
    $this->postJson("/api/balances/{$balance->id}/reconcile", [
        'reconciled_amount' => 250_000,
        'reconciled_at' => '2026-08-25',
    ])->assertOk();

    expect($this->getJson('/api/balances')->json('0.is_drift_flagged'))->toBeTrue();

    // Recount matches the ledger → same card goes back to green.
    $this->postJson("/api/balances/{$balance->id}/reconcile", [
        'reconciled_amount' => 300_000,
        'reconciled_at' => '2026-08-26',
    ])->assertOk();

    $listItem = $this->getJson('/api/balances')->json('0');
    expect($listItem['is_drift_flagged'])->toBeFalse();
    expect($listItem['drift'])->toBe(0);
});

test('transactions after reconciled_at do not trigger false drift', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'initial_amount' => 1_000_000,
        'final_amount' => 1_000_000,
    ]);

    Sanctum::actingAs($user, ['balances:read', 'balances:write']);

    // Reconcile on 2026-08-20 with exact match
    $this->postJson("/api/balances/{$balance->id}/reconcile", [
        'reconciled_amount' => 1_000_000,
        'reconciled_at' => '2026-08-20',
    ])->assertOk();

    expect($balance->fresh()->drift)->toBe(0);
    expect($balance->fresh()->is_drift_flagged)->toBeFalse();

    // Now log an expense on 2026-08-21 (after reconciliation date)
    SaveTransaction::run(new Transaction, TransactionData::from([
        'user_id' => $user->id,
        'balance_id' => $balance->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => CarbonImmutable::parse('2026-08-21'),
        'amount' => 50_000,
        'description' => 'Normal post-reconcile expense',
    ]));

    $fresh = $balance->fresh();
    expect($fresh->final_amount)->toBe(950_000);
    // Point-in-time drift remains 0, NOT -50_000!
    expect($fresh->drift)->toBe(0);
    expect($fresh->is_drift_flagged)->toBeFalse();
});

test('transactions created on the same date as reconciled_at after reconciliation do not trigger false drift', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'initial_amount' => 1_000_000,
        'final_amount' => 1_000_000,
    ]);

    Sanctum::actingAs($user, ['balances:read', 'balances:write']);

    // Reconcile today with exact match
    $today = now()->toDateString();
    $this->postJson("/api/balances/{$balance->id}/reconcile", [
        'reconciled_amount' => 1_000_000,
        'reconciled_at' => $today,
    ])->assertOk();

    expect($balance->fresh()->drift)->toBe(0);
    expect($balance->fresh()->is_drift_flagged)->toBeFalse();

    // Now log an expense on the SAME date (e.g. afternoon expense after morning reconcile)
    Carbon::setTestNow(now()->addSecond());

    SaveTransaction::run(new Transaction, TransactionData::from([
        'user_id' => $user->id,
        'balance_id' => $balance->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => CarbonImmutable::parse($today),
        'amount' => 50_000,
        'description' => 'Same-day post-reconcile expense',
    ]));

    $fresh = $balance->fresh();
    expect($fresh->final_amount)->toBe(950_000);
    // Point-in-time drift remains 0, NOT -50_000!
    expect($fresh->drift)->toBe(0);
    expect($fresh->is_drift_flagged)->toBeFalse();
});

test('api reconcile with auto_adjust creates adjustment transaction and zeroes drift', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'initial_amount' => 1_000_000,
        'final_amount' => 1_000_000,
    ]);

    Sanctum::actingAs($user, ['balances:read', 'balances:write']);

    // Actual statement is 920_000 (drift = +80_000, ledger higher than statement)
    $res = $this->postJson("/api/balances/{$balance->id}/reconcile", [
        'reconciled_amount' => 920_000,
        'reconciled_at' => now()->toDateString(),
        'auto_adjust' => true,
    ])->assertOk();

    expect($res->json('drift'))->toBe(0);
    expect($res->json('is_drift_flagged'))->toBeFalse();
    expect($res->json('final_amount'))->toBe(920_000);

    $fresh = $balance->fresh();
    expect($fresh->drift)->toBe(0);
    expect($fresh->is_drift_flagged)->toBeFalse();
    expect($fresh->final_amount)->toBe(920_000);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'balance_id' => $balance->id,
        'amount' => 80_000,
        'type' => 'expense',
        'description' => 'Penyesuaian saldo rekonsiliasi',
    ]);
});
