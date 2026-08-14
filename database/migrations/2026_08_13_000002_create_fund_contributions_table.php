<?php

use App\Models\SinkingFund;
use App\Models\Transaction;
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
        Schema::create('fund_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SinkingFund::class, 'fund_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('type', 20); // contribution | withdrawal
            $table->bigInteger('amount'); // always positive; sign comes from type
            $table->date('date');
            $table->foreignIdFor(Transaction::class)->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();

            // Covers both sides of the progress SUM in one scan:
            // (user_id, fund_id, type, date) -> accumulated contributions/withdrawals.
            $table->index(['user_id', 'fund_id', 'type', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_contributions');
    }
};
