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
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_webhook_url')->nullable()->after('remember_token');
        });

        Schema::table('budget_items', function (Blueprint $table) {
            $table->boolean('alert_80_sent')->default(false)->after('planned_amount');
            $table->boolean('alert_100_sent')->default(false)->after('alert_80_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropColumn(['alert_80_sent', 'alert_100_sent']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('discord_webhook_url');
        });
    }
};
