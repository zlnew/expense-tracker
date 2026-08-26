<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_contributions', function (Blueprint $table) {
            // Shared key linking a withdrawal ledger row to its paired expense
            // transaction (the expense carries the same uuid in
            // transactions.transfer_group_id). Future-dated rows stay null;
            // contributions never share a group.
            $table->string('group_id', 36)->nullable()->index()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('fund_contributions', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
