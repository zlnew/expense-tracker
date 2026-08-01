<?php

namespace App\Actions;

use App\Models\User;
use App\Support\DefaultCategories;

class SeedDefaultCategories extends Action
{
    public function __construct(
        private readonly User $user,
    ) {}

    public function handle(): void
    {
        $existing = $this->user->categories()->pluck('name', 'type')->map(fn ($name) => strtolower($name));

        foreach (DefaultCategories::all() as $category) {
            // Skip if this user already has a category with the same type+name
            // (idempotent seeding for existing accounts).
            $duplicate = $this->user->categories()
                ->where('type', $category['type'])
                ->whereRaw('LOWER(name) = ?', [strtolower($category['name'])])
                ->exists();

            if ($duplicate) {
                continue;
            }

            $this->user->categories()->create([
                'type' => $category['type'],
                'name' => $category['name'],
            ]);
        }
    }
}
