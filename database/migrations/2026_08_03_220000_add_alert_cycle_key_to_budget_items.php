<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budget alerts are per-cycle, not once-ever.
 *
 * Previously alert_80_sent / alert_100_sent were permanent flags: once an
 * alert fired it never fired again, and CheckBudgetAlerts summed ALL-TIME
 * spend. Fixing the sum to the current cycle requires knowing WHICH cycle
 * the flags belong to — this column records the cycle start date they were
 * set in, so a new cycle re-arms the alerts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->string('alert_cycle_key')->nullable()->after('alert_100_sent');
        });
    }

    public function down(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropColumn('alert_cycle_key');
        });
    }
};
