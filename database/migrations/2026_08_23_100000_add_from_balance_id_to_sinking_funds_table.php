<?php

use App\Support\BackfillFundSourceBalance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sinking_funds', function (Blueprint $table) {
            $table->foreignId('from_balance_id')
                ->nullable()
                ->after('user_id')
                ->constrained('balances')
                ->nullOnDelete();
            $table->index('from_balance_id');
        });

        // Backfill existing funds to the user's primary balance (M1 decision §11.4).
        // One-time, idempotent: only touches rows where from_balance_id is still null.
        BackfillFundSourceBalance::run();
    }

    public function down(): void
    {
        Schema::table('sinking_funds', function (Blueprint $table) {
            $table->dropForeign(['from_balance_id']);
            $table->dropIndex(['from_balance_id']);
            $table->dropColumn('from_balance_id');
        });
    }
};
