<?php

namespace App\Mcp\Resources;

use App\Actions\GetImpendingDrains;
use App\Models\User;

class ImpendingDrainsResource implements ResourceInterface
{
    public function uri(): string
    {
        return 'expense-tracker://impending-drains';
    }

    public function name(): string
    {
        return 'Impending Cash Outflows Queue';
    }

    public function description(): string
    {
        return 'Upcoming 60-day cash outflow stream (sinking fund dues and recurring bills) with projected balances.';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function read(User $user): string
    {
        $drains = GetImpendingDrains::run($user->id, 60);

        return json_encode($drains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
