<?php

use App\Models\Category;
use App\Models\User;
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
        Schema::create('sinking_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('target_amount');
            $table->string('cadence', 20)->default('cycle'); // cycle | monthly
            $table->bigInteger('contribution_amount')->nullable(); // null = auto-computed
            $table->foreignIdFor(Category::class)->nullable()->constrained()->nullOnDelete();
            $table->date('next_due')->nullable();
            $table->unsignedSmallInteger('due_interval_months')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinking_funds');
    }
};
