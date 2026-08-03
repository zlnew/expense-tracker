<?php

use App\Actions\CheckBudgetAlerts;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Bug (2026-08-03): CheckBudgetAlerts summed ALL-TIME expenses for a budget
 * item, so last cycle's spend inflated the current cycle's percentage —
 * e.g. a budget of 100k with 100k spent last month and 10k this month
 * reported 110% instead of 10%.
 */
test('budget alert uses only the current cycle spend, not all-time', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => 'https://discord.com/api/webhooks/123']);
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food']);
    $budget = Budget::factory()->for($user)->create(['cutoff_day' => 1]);
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 100_000,
    ]);

    // Last cycle: already blew the whole budget (100k of 100k).
    Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 100_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => Carbon::now()->startOfMonth()->subMonthNoOverflow()->addDays(5),
    ]);

    // This cycle: only 10k spent.
    $transaction = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 10_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => Carbon::now()->startOfMonth()->addDays(5),
    ]);

    CheckBudgetAlerts::run($user, $transaction);

    Http::assertNothingSent();
});

/**
 * Companion fix: alert flags must be per-cycle. Once 80% fired in cycle A,
 * it must be able to fire again in cycle B (previously the flag stayed true
 * forever, so a cycle-scoped sum would have silenced all future alerts).
 */
test('budget alert fires again in a new cycle after firing in a previous one', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => 'https://discord.com/api/webhooks/123']);
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food']);
    $budget = Budget::factory()->for($user)->create(['cutoff_day' => 1]);
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 100_000,
    ]);

    // Cycle A (e.g. July 3): cross 80% → first webhook.
    Carbon::setTestNow(Carbon::parse('2026-07-03 12:00:00'));
    $first = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 80_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => Carbon::now()->startOfMonth()->addDays(5),
    ]);

    CheckBudgetAlerts::run($user, $first);
    Http::assertSentCount(1);

    // Cycle B (e.g. Aug 3): cross 80% again → must send a fresh webhook.
    Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00'));
    $second = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 80_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => Carbon::now()->startOfMonth()->addDays(5),
    ]);

    CheckBudgetAlerts::run($user, $second);
    Http::assertSentCount(2);
    expect($item->fresh()->alert_80_sent)->toBeTrue();
    expect($item->fresh()->alert_cycle_key)->toBe('2026-08-02');
});
