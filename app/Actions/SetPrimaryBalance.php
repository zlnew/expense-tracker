<?php

namespace App\Actions;

use App\Models\Balance;
use Illuminate\Support\Facades\DB;

class SetPrimaryBalance extends Action
{
    public function __construct(
        private readonly Balance $balance,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $userId = $this->balance->user_id;

            Balance::query()->where('user_id', $userId)->update(['is_primary' => false]);

            $this->balance->is_primary = true;
            $this->balance->save();
        });
    }
}
