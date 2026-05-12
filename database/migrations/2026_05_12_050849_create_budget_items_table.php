<?php

use App\Models\Budget;
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
        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Budget::class)->constrained();
            $table->foreignIdFor(Category::class)->constrained();
            $table->string('type');
            $table->bigInteger('planned_amount')->default(0);
            $table->bigInteger('actual_amount')->default(0);
            $table->bigInteger('diff_amount')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
