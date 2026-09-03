<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects a non-positive transaction amount', function (int $amount) {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'balance_id' => $balance->id,
            'type' => CategoryType::EXPENSE->value,
            'date' => now()->toDateString(),
            'amount' => $amount,
        ])
        ->assertSessionHasErrors('amount');
})->with([0, -1, -100_000]);

it('rejects a non-positive transfer amount', function (int $amount) {
    $user = User::factory()->create();
    $from = Balance::factory()->for($user)->create(['initial_amount' => 100_000]);
    $to = Balance::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('transactions.transfer-between-accounts'), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'date' => now()->toDateString(),
            'amount' => $amount,
        ])
        ->assertSessionHasErrors('amount');
})->with([0, -1, -100_000]);
