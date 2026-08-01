<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Support\DefaultCategories;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the default category set.
     *
     * Before per-user categories (2026-08) this created global rows. Now the
     * canonical list lives in App\Support\DefaultCategories and each user gets
     * a private copy via SeedDefaultCategories on registration. This seeder
     * keeps the table usable for tests/dev by seeding for the first user if
     * one exists and no categories are present.
     */
    public function run(): void
    {
        $userId = \App\Models\User::query()->value('id');

        if ($userId === null || Category::query()->where('user_id', $userId)->exists()) {
            return;
        }

        foreach (DefaultCategories::all() as $category) {
            Category::query()->create([
                'user_id' => $userId,
                'type' => $category['type'],
                'name' => $category['name'],
            ]);
        }
    }
}
