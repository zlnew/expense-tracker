<?php

use App\Actions\SyncFinancialIntegrity;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            SyncFinancialIntegrity::run($user, isDryRun: false, pruneZeroBudgetItems: true);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data integrity synchronization and orphaned envelope pruning cannot and need not be reversed.
    }
};
