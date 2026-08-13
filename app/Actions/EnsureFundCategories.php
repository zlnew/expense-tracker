<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\User;

/**
 * Create the Maintenance + Taxes expense categories for a user when missing.
 *
 * The sinking-funds module charges withdrawals to real expense categories
 * (D3), but categories are per-user since 2026_08_01_202000 and can't be
 * assumed to exist. Guarded by user_id + type + name (case-insensitive),
 * exactly the SeedDefaultCategories discipline — idempotent for existing
 * accounts and safe to run standalone (tinker) on the data-fix card.
 */
class EnsureFundCategories extends Action
{
    public const MAINTENANCE = 'Maintenance';

    public const TAXES = 'Taxes';

    public function __construct(
        private readonly User $user,
    ) {}

    public function handle(): void
    {
        foreach ([self::MAINTENANCE, self::TAXES] as $name) {
            $exists = $this->user->categories()
                ->where('type', CategoryType::EXPENSE->value)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->exists();

            if ($exists) {
                continue;
            }

            $this->user->categories()->create([
                'type' => CategoryType::EXPENSE->value,
                'name' => $name,
            ]);
        }
    }
}
