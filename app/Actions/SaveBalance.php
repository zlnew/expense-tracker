<?php

namespace App\Actions;

use App\DTO\BalanceData;
use App\Models\Balance;
use Illuminate\Support\Facades\Auth;

class SaveBalance extends Action
{
    public function __construct(
        private readonly Balance $balance,
        private readonly BalanceData $data,
    ) {}

    public function handle()
    {
        $userId = Auth::id();

        if (! $this->balance->user_id) {
            $this->balance->user()->associate($userId);
        }

        $this->balance->initial_amount = $this->data->initial_amount;
        $this->balance->save();
    }
}
