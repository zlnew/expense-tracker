<?php

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['budget_id']);
            $table->dropForeign(['budget_item_id']);
            $table->dropForeign(['category_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignIdFor(Budget::class)->nullable()->change()->constrained();
            $table->foreignIdFor(BudgetItem::class)->nullable()->change()->constrained();
            $table->foreignIdFor(Category::class)->nullable()->change()->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['budget_id']);
            $table->dropForeign(['budget_item_id']);
            $table->dropForeign(['category_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignIdFor(Budget::class)->nullable(false)->change()->constrained();
            $table->foreignIdFor(BudgetItem::class)->nullable(false)->change()->constrained();
            $table->foreignIdFor(Category::class)->nullable(false)->change()->constrained();
        });
    }
};
