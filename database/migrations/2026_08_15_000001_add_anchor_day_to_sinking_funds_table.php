<?php

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
        Schema::table('sinking_funds', function (Blueprint $table) {
            // The day-of-month the cadence is anchored to. next_due rolls
            // forward from THIS day (clamped to the target month's length),
            // so a fund due on the 31st doesn't drift to the 28th after the
            // first short month. null for legacy rows → fall back to the
            // current next_due day on the next roll.
            $table->unsignedSmallInteger('anchor_day')->nullable()->after('next_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sinking_funds', function (Blueprint $table) {
            $table->dropColumn('anchor_day');
        });
    }
};
