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
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('fires Discord webhook once at 80% budget usage', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => 'https://discord.com/api/webhooks/123']);
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food']);
    $budget = Budget::factory()->for($user)->create();
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 100_000,
    ]);

    // 80k of a 100k budget => exactly 80%.
    $transaction = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 80_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => now(),
    ]);

    CheckBudgetAlerts::run($user, $transaction);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://discord.com/api/webhooks/123'
            && str_contains($request['content'], '80%')
            && str_contains($request['content'], 'Food');
    });

    expect($item->fresh()->alert_80_sent)->toBeTrue();
});

test('fires Discord webhook at 100% and only once for both thresholds', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => 'https://discord.com/api/webhooks/123']);
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food']);
    $budget = Budget::factory()->for($user)->create();
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 100_000,
    ]);

    // First hit 100% directly (no 80% alert in between => only the 100% alert).
    $transaction = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 100_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => now(),
    ]);

    CheckBudgetAlerts::run($user, $transaction);

    Http::assertSentCount(1);
    expect($item->fresh()->alert_100_sent)->toBeTrue();

    // Another expense crossing 100% again must NOT send a second webhook.
    $transaction2 = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 10_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => now(),
    ]);

    CheckBudgetAlerts::run($user, $transaction2);

    Http::assertSentCount(1);
});

test('fires 80% alert then a separate 100% alert on later expenses', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => 'https://discord.com/api/webhooks/123']);
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food']);
    $budget = Budget::factory()->for($user)->create();
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 100_000,
    ]);

    // 80k => 80% alert.
    $transaction = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 80_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => now(),
    ]);

    CheckBudgetAlerts::run($user, $transaction);

    Http::assertSentCount(1);
    expect($item->fresh()->alert_80_sent)->toBeTrue();
    expect($item->fresh()->alert_100_sent)->toBeFalse();

    // Another 20k => 100% alert (second webhook, distinct message).
    $transaction2 = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 20_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => now(),
    ]);

    CheckBudgetAlerts::run($user, $transaction2);

    Http::assertSentCount(2);
    expect($item->fresh()->alert_100_sent)->toBeTrue();
});

test('does not fire webhook when no webhook URL is configured', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => null]);
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food']);
    $budget = Budget::factory()->for($user)->create();
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 100_000,
    ]);

    $transaction = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 100_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => now(),
    ]);

    CheckBudgetAlerts::run($user, $transaction);

    Http::assertNothingSent();
});

test('does not fire webhook for expenses under 80%', function () {
    Http::fake();

    $user = User::factory()->create(['discord_webhook_url' => 'https://discord.com/api/webhooks/123']);
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food']);
    $budget = Budget::factory()->for($user)->create();
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 100_000,
    ]);

    $transaction = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 50_000,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'date' => now(),
    ]);

    CheckBudgetAlerts::run($user, $transaction);

    Http::assertNothingSent();
    expect($item->fresh()->alert_80_sent)->toBeFalse();
});
