<?php

use App\Actions\GetBalanceInsight;
use App\Actions\SaveFund;
use App\Actions\SaveFundContribution;
use App\DTO\FundContributionData;
use App\DTO\FundData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\SinkingFund;
use App\Models\User;
use App\Support\BackfillFundSourceBalance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
function realMakeFundData(array $o = []): FundData
{
    return FundData::from(array_merge(['name' => 'Moto', 'target_amount' => 400000, 'cadence' => 'cycle', 'contribution_amount' => null, 'category_id' => null, 'from_balance_id' => null, 'next_due' => null, 'due_interval_months' => 1, 'notes' => null], $o));
}
function realMakeContrib(array $o = []): FundContributionData
{
    return FundContributionData::from(array_merge(['amount' => 50000, 'date' => CarbonImmutable::now()->toDateString(), 'description' => 'set-aside'], $o));
}
function realExpenseCategory(User $u): Category
{
    return Category::factory()->for($u)->create(['type' => CategoryType::EXPENSE, 'name' => 'Maint']);
}

test('reserved sums reserves of funds whose from_balance_id is this balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $b1 = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $b2 = Balance::factory()->for($user)->create(['initial_amount' => 500_000, 'final_amount' => 500_000]);
    $cat = realExpenseCategory($user);
    $f1 = SaveFund::run(new SinkingFund, realMakeFundData(['category_id' => $cat->id, 'from_balance_id' => $b1->id, 'target_amount' => 200_000]));
    $f2 = SaveFund::run(new SinkingFund, realMakeFundData(['category_id' => $cat->id, 'from_balance_id' => $b1->id, 'target_amount' => 100_000]));
    $fOther = SaveFund::run(new SinkingFund, realMakeFundData(['category_id' => $cat->id, 'from_balance_id' => $b2->id, 'target_amount' => 300_000]));
    SaveFundContribution::run($f1, realMakeContrib(['amount' => 80_000]));
    SaveFundContribution::run($f2, realMakeContrib(['amount' => 40_000]));
    SaveFundContribution::run($fOther, realMakeContrib(['amount' => 60_000]));
    $insight1 = GetBalanceInsight::run($b1);
    $insight2 = GetBalanceInsight::run($b2);
    expect($insight1['reserved'])->toBe(120_000)
        ->and($insight2['reserved'])->toBe(60_000);
});

test('real equals final minus reserved', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 800_000]);
    $cat = realExpenseCategory($user);
    $fund = SaveFund::run(new SinkingFund, realMakeFundData(['category_id' => $cat->id, 'from_balance_id' => $balance->id]));
    SaveFundContribution::run($fund, realMakeContrib(['amount' => 30_000]));
    $insight = GetBalanceInsight::run($balance);
    expect($insight['reserved'])->toBe(30_000)
        ->and($insight['real'])->toBe(770_000)
        ->and($insight['active'])->toBe(800_000);
    expect($balance->reserved)->toBe(30_000)
        ->and($balance->real)->toBe(770_000);
});

test('null from_balance_id on new fund via API returns 422', function () {
    $user = User::factory()->create();
    $cat = realExpenseCategory($user);
    $this->actingAs($user)->postJson('/api/funds', [
        'name' => 'No Source',
        'target_amount' => 100_000,
        'cadence' => 'cycle',
        'category_id' => $cat->id,
        'from_balance_id' => null,
        'due_interval_months' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('from_balance_id');
    $this->actingAs($user)->post(route('funds.store'), [
        'name' => 'No Source',
        'target_amount' => 100_000,
        'cadence' => 'cycle',
        'category_id' => $cat->id,
        'from_balance_id' => null,
        'due_interval_months' => 1,
    ])->assertSessionHasErrors('from_balance_id');
});

test('real recomputes when a fund reserve changes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $cat = realExpenseCategory($user);
    $fund = SaveFund::run(new SinkingFund, realMakeFundData(['category_id' => $cat->id, 'from_balance_id' => $balance->id]));
    SaveFundContribution::run($fund, realMakeContrib(['amount' => 50_000]));
    expect(GetBalanceInsight::run($balance)['real'])->toBe(950_000);
    SaveFundContribution::run($fund, realMakeContrib(['amount' => 25_000]));
    expect(GetBalanceInsight::run($balance)['real'])->toBe(925_000);
});

test('net worth is sum of real across balances', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $b1 = Balance::factory()->for($user)->create(['initial_amount' => 1_000_000, 'final_amount' => 1_000_000]);
    $b2 = Balance::factory()->for($user)->create(['initial_amount' => 400_000, 'final_amount' => 400_000]);
    $cat = realExpenseCategory($user);
    $f = SaveFund::run(new SinkingFund, realMakeFundData(['category_id' => $cat->id, 'from_balance_id' => $b1->id]));
    SaveFundContribution::run($f, realMakeContrib(['amount' => 100_000]));
    $netWorth = GetBalanceInsight::netWorth($user);
    expect($netWorth)->toBe(1_300_000); // (1_000_000-100_000)+400_000
});

test('dashboard headlines real; total_balance equals sum real', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $b = Balance::factory()->for($user)->create(['initial_amount' => 600_000, 'final_amount' => 600_000]);
    $cat = realExpenseCategory($user);
    $fund = SaveFund::run(new SinkingFund, realMakeFundData(['category_id' => $cat->id, 'from_balance_id' => $b->id, 'target_amount' => 100_000]));
    SaveFundContribution::run($fund, realMakeContrib(['amount' => 80_000]));
    $this->get(route('dashboard'))->assertInertia(fn ($p) => $p
        ->where('summary_cards.total_balance', 520_000)
        ->where('summary_cards.total_active', 600_000)
        ->where('summary_cards.total_reserved', 80_000)
    );
});

test('web balance list serves reserved and real legs', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 600_000, 'final_amount' => 600_000]);
    $cat = realExpenseCategory($user);
    $fund = SaveFund::run(new SinkingFund, realMakeFundData(['category_id' => $cat->id, 'from_balance_id' => $balance->id]));
    SaveFundContribution::run($fund, realMakeContrib(['amount' => 80_000]));

    $this->get(route('balances.index'))->assertInertia(fn ($p) => $p
        ->where('balances.data.0.reserved', 80_000)
        ->where('balances.data.0.real', 520_000)
    );
});

test('api balance list serves reserved and real legs', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['balances:read']);
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 500_000, 'final_amount' => 500_000]);
    $cat = realExpenseCategory($user);
    $fund = SaveFund::run(new SinkingFund, realMakeFundData(['category_id' => $cat->id, 'from_balance_id' => $balance->id]));
    SaveFundContribution::run($fund, realMakeContrib(['amount' => 50_000]));

    $this->getJson('/api/balances')
        ->assertOk()
        ->assertJsonPath('0.reserved', 50_000)
        ->assertJsonPath('0.real', 450_000);
});

test('backfill anchors legacy funds to the primary balance', function () {
    $user = User::factory()->create();
    $primary = Balance::factory()->for($user)->create(['is_primary' => true, 'final_amount' => 900_000]);
    Balance::factory()->for($user)->create(['final_amount' => 100_000]);

    // Simulate a legacy row created before the column existed.
    $legacyId = DB::table('sinking_funds')->insertGetId([
        'user_id' => $user->id,
        'name' => 'Legacy Fund',
        'target_amount' => 200_000,
        'cadence' => 'cycle',
        'contribution_amount' => null,
        'category_id' => null,
        'next_due' => null,
        'due_interval_months' => 1,
        'anchor_day' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(BackfillFundSourceBalance::run())->toBe(1);

    $linked = DB::table('sinking_funds')->where('id', $legacyId)->value('from_balance_id');
    expect($linked)->toBe($primary->id);

    // Idempotent: second pass touches nothing.
    expect(BackfillFundSourceBalance::run())->toBe(0);
});

test('backfill falls back to oldest balance when no primary is flagged', function () {
    $user = User::factory()->create();
    $oldest = Balance::factory()->for($user)->create(['final_amount' => 300_000]);
    Balance::factory()->for($user)->create(['final_amount' => 700_000]);

    $legacyId = DB::table('sinking_funds')->insertGetId([
        'user_id' => $user->id,
        'name' => 'Legacy No Primary',
        'target_amount' => 150_000,
        'cadence' => 'cycle',
        'contribution_amount' => null,
        'category_id' => null,
        'next_due' => null,
        'due_interval_months' => 1,
        'anchor_day' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(BackfillFundSourceBalance::run())->toBe(1);

    $linked = DB::table('sinking_funds')->where('id', $legacyId)->value('from_balance_id');
    expect($linked)->toBe($oldest->id);
});
