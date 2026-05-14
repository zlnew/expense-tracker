<?php

namespace App\Actions;

use App\DTO\BalanceData;
use App\Models\Balance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaveBalance extends Action
{
    public function __construct(
        private readonly Balance $balance,
        private readonly BalanceData $data,
    ) {}

    public function handle()
    {
        DB::transaction(function () {

            $userId = Auth::id();

            if (! $this->balance->user_id) {
                $this->balance->user()->associate($userId);
            }

            $this->balance->name = $this->data->name;
            $this->balance->description = $this->data->description;
            $this->balance->initial_amount = $this->data->initial_amount;
            $this->balance->save();

            SyncBalance::run($this->balance->id);
        });
    }
}
