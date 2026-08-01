<?php

namespace App\Console\Commands;

use App\Actions\ProcessRecurringTransactions;
use Illuminate\Console\Command;

class ProcessRecurringTransactionsCommand extends Command
{
    protected $signature = 'recurring:process';

    protected $description = 'Create transactions for due recurring transactions and advance their schedules';

    public function handle(ProcessRecurringTransactions $process): int
    {
        $count = $process->handle();

        $this->info("Processed {$count} recurring transaction(s).");

        return self::SUCCESS;
    }
}
