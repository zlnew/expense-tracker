<?php

use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Backfill: give every existing user a private clone of the current
        // global categories, then remap their budget items + transactions to
        // the clones so no reference dangles.
        $globalCategories = Category::query()->whereNull('user_id')->get();

        foreach (User::all() as $user) {
            $mapping = [];

            foreach ($globalCategories as $global) {
                $clone = Category::create([
                    'user_id' => $user->id,
                    'type' => $global->type,
                    'name' => $global->name,
                ]);

                $mapping[$global->id] = $clone->id;
            }

            foreach ($mapping as $oldId => $newId) {
                BudgetItem::query()
                    ->whereHas('budget', fn ($q) => $q->where('user_id', $user->id))
                    ->where('category_id', $oldId)
                    ->update(['category_id' => $newId]);

                Transaction::query()
                    ->where('user_id', $user->id)
                    ->where('category_id', $oldId)
                    ->update(['category_id' => $newId]);
            }
        }

        // Remove the shared global rows now that every user has their own.
        Category::query()->whereNull('user_id')->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Relax NOT NULL first so rows can be merged back to the shared set.
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        // Re-merge per-user categories back into one shared set (best effort;
        // keeps the first matching name/type per row, drops duplicates).
        $seen = [];

        foreach (Category::orderBy('user_id')->get() as $category) {
            $key = $category->type->value.'|'.$category->name;

            if (isset($seen[$key])) {
                $target = $seen[$key];

                BudgetItem::query()->where('category_id', $category->id)->update(['category_id' => $target->id]);
                Transaction::query()->where('category_id', $category->id)->update(['category_id' => $target->id]);

                $category->delete();

                continue;
            }

            $category->user_id = null;
            $category->save();
            $seen[$key] = $category;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
