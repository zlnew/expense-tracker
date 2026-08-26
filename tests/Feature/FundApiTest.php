<?php

use App\Actions\SaveFund;
use App\Actions\SaveFundContribution;
use App\DTO\FundContributionData;
use App\DTO\FundData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\SinkingFund;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function fundApiToken(User $user, string $abilities): string
{
    return $user->createToken('test', explode(',', $abilities))->plainTextToken;
}

function apiExpenseCategory(User $user): Category
{
    return Category::factory()->for($user)->create([
        'type' => CategoryType::EXPENSE,
        'name' => 'Maintenance',
    ]);
}

function apiMakeFundData(User $user, array $overrides = []): FundData
{
    $defaults = ['name' => 'Moto', 'target_amount' => 400_000, 'cadence' => 'cycle', 'due_interval_months' => 2];
    if (! array_key_exists('category_id', $overrides)) {
        $cat = Category::query()->where('user_id', $user->id)->where('type', CategoryType::EXPENSE->value)->first();
        if (! $cat) {
            $cat = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maintenance']);
        }
        $defaults['category_id'] = $cat->id;
    }
    if (! array_key_exists('from_balance_id', $overrides)) {
        $bal = Balance::query()->where('user_id', $user->id)->first();
        if (! $bal) {
            $bal = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
        }
        $defaults['from_balance_id'] = $bal->id;
    }
    $merged = array_merge($defaults, $overrides);
    if (array_key_exists('category_id', $overrides)) {
        $merged['category_id'] = $overrides['category_id'];
    }
    if (array_key_exists('from_balance_id', $overrides)) {
        $merged['from_balance_id'] = $overrides['from_balance_id'];
    }

    return FundData::from($merged);
}

test('unauthenticated requests to every fund api endpoint return 401', function () {
    $this->getJson('/api/funds')->assertUnauthorized();
    $this->postJson('/api/funds', [])->assertUnauthorized();
    $this->getJson('/api/funds/upcoming')->assertUnauthorized();
    $this->postJson('/api/funds/1/contributions', [])->assertUnauthorized();
    $this->postJson('/api/funds/1/withdrawals', [])->assertUnauthorized();
    $this->patchJson('/api/funds/1', [])->assertUnauthorized();
    $this->deleteJson('/api/funds/1')->assertUnauthorized();
});

test('fund endpoints enforce abilities (read vs write)', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['funds:write']);
    $this->getJson('/api/funds')->assertForbidden();
    $this->getJson('/api/funds/upcoming')->assertForbidden();

    Sanctum::actingAs($user, ['funds:read']);
    $this->getJson('/api/funds')->assertOk();
    $this->postJson('/api/funds', [
        'name' => 'Moto',
        'target_amount' => 400_000,
        'cadence' => 'cycle',
        'due_interval_months' => 2,
    ])->assertForbidden();
});

test('creating a fund via the api returns 201 with progress and the picked category', function () {
    $user = User::factory()->create();
    $token = fundApiToken($user, 'funds:write');
    $category = apiExpenseCategory($user);
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/funds', [
            'name' => 'Moto Maintenance',
            'target_amount' => 400_000,
            'cadence' => 'cycle',
            'contribution_amount' => null,
            'category_id' => $category->id,
            'from_balance_id' => $balance->id,
            'next_due' => CarbonImmutable::now()->addMonths(2)->toDateString(),
            'due_interval_months' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Moto Maintenance')
        ->assertJsonPath('target_amount', 400_000)
        ->assertJsonPath('category_id', $category->id)
        ->assertJsonPath('accumulated', 0)
        ->assertJsonPath('percent', 0)
        ->assertJsonPath('status', 'on_track');

    // Only the user-picked category exists — no auto-created Maintenance/Taxes.
    expect($user->categories()->count())->toBe(1);
});

test('creating a fund via the api without a category is rejected', function () {
    $user = User::factory()->create();
    $token = fundApiToken($user, 'funds:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/funds', [
            'name' => 'No Category',
            'target_amount' => 400_000,
            'cadence' => 'cycle',
            'due_interval_months' => 2,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');
});

test('a fund is scoped to its user in every endpoint', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    // Create through the owner's own API token (exercises the store path and
    // avoids the actingAs default-guard leak documented in ApiTest).
    $ownerToken = fundApiToken($owner, 'funds:read,funds:write');
    $category = apiExpenseCategory($owner);
    $ownerBalance = Balance::factory()->for($owner)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);

    $created = $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->postJson('/api/funds', [
            'name' => 'Owner Fund',
            'target_amount' => 400_000,
            'cadence' => 'cycle',
            'category_id' => $category->id,
            'from_balance_id' => $ownerBalance->id,
            'due_interval_months' => 1,
        ])
        ->assertCreated();

    $fundId = $created->json('id');

    // Sanctum::actingAs per request avoids the RequestGuard user cache
    // leaking the owner identity across requests in one test (ApiTest doc).
    Sanctum::actingAs($intruder, ['funds:read', 'funds:write']);
    $this->getJson('/api/funds')
        ->assertOk()
        ->assertJsonCount(0);

    Sanctum::actingAs($intruder, ['funds:write']);
    $this->patchJson("/api/funds/{$fundId}", ['name' => 'hijacked'])
        ->assertNotFound();

    Sanctum::actingAs($intruder, ['funds:write']);
    $this->deleteJson("/api/funds/{$fundId}")
        ->assertNotFound();

    Sanctum::actingAs($intruder, ['funds:write']);
    $this->postJson("/api/funds/{$fundId}/contributions", [
        'amount' => 10_000,
        'date' => now()->toDateString(),
    ])->assertNotFound();
});

test('set-aside via api creates no transaction and updates progress', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $fund = SaveFund::run(new SinkingFund, apiMakeFundData($user));

    $token = fundApiToken($user, 'funds:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/funds/{$fund->id}/contributions", [
            'amount' => 50_000,
            'date' => now()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('accumulated', 50_000)
        ->assertJsonPath('percent', 13);

    expect(Transaction::count())->toBe(0);
});

test('withdrawal via api creates a real expense and returns progress', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $fund = SaveFund::run(new SinkingFund, apiMakeFundData($user));
    SaveFundContribution::run($fund, FundContributionData::from([
        'amount' => 200_000,
        'date' => now()->toDateString(),
    ]));

    $token = fundApiToken($user, 'funds:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/funds/{$fund->id}/withdrawals", [
            'amount' => 150_000,
            'balance_id' => $balance->id,
            'date' => now()->toDateString(),
            'description' => 'Servis Motor',
        ])
        ->assertCreated()
        ->assertJsonPath('accumulated', 50_000);

    expect(Transaction::count())->toBe(1);
});

test('withdrawal above the reserve returns 422 insufficient_fund_reserve', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $fund = SaveFund::run(new SinkingFund, apiMakeFundData($user));
    SaveFundContribution::run($fund, FundContributionData::from([
        'amount' => 50_000,
        'date' => now()->toDateString(),
    ]));

    $token = fundApiToken($user, 'funds:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/funds/{$fund->id}/withdrawals", [
            'amount' => 60_000,
            'balance_id' => $balance->id,
            'date' => now()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount')
        ->assertJsonPath('errors.amount.0', 'Insufficient fund reserve');
});

test('upcoming endpoint returns due funds with days_until_due and shortfall', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $fund = SaveFund::run(new SinkingFund, apiMakeFundData($user, [
        'name' => 'Moto Maintenance',
        'target_amount' => 400_000,
        'next_due' => CarbonImmutable::now()->addDays(10)->toDateString(),
        'due_interval_months' => 2,
    ]));
    SaveFundContribution::run($fund, FundContributionData::from([
        'amount' => 200_000,
        'date' => now()->toDateString(),
    ]));

    $token = fundApiToken($user, 'funds:read');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/funds/upcoming')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Moto Maintenance')
        ->assertJsonPath('0.days_until_due', 10)
        ->assertJsonPath('0.accumulated', 200_000);

    // Horizon override narrows it away.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/funds/upcoming?horizon=5')
        ->assertOk()
        ->assertJsonCount(0);
});

test('patching a fund updates only the sent fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $fund = SaveFund::run(new SinkingFund, apiMakeFundData($user));

    $token = fundApiToken($user, 'funds:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/funds/{$fund->id}", ['target_amount' => 500_000])
        ->assertOk()
        ->assertJsonPath('target_amount', 500_000)
        ->assertJsonPath('name', 'Moto');

    // A fund with a next_due can be detached via explicit null.
    $fund2 = SaveFund::run(new SinkingFund, apiMakeFundData($user, [
        'name' => 'Taxes',
        'target_amount' => 300_000,
        'next_due' => now()->addMonth()->toDateString(),
        'due_interval_months' => 12,
    ]));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/funds/{$fund2->id}", ['next_due' => null])
        ->assertOk()
        ->assertJsonPath('next_due', null);
});

test('deleting a fund soft-deletes it', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $fund = SaveFund::run(new SinkingFund, apiMakeFundData($user));

    $token = fundApiToken($user, 'funds:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/funds/{$fund->id}")
        ->assertNoContent();

    expect(SinkingFund::count())->toBe(0)
        ->and(SinkingFund::withTrashed()->count())->toBe(1);
});
