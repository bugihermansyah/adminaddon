<?php

namespace App\Console\Commands;

use App\Services\ResidentCsvSyncDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:dispatch-due-resident-csv-syncs')]
#[Description('Dispatch due resident CSV imports to the background queue')]
class DispatchDueResidentCsvSyncs extends Command
{
    public function handle(ResidentCsvSyncDispatcher $dispatcher): int
    {
        $result = $dispatcher->dispatchDue();

        if ($result['queued_jobs'] === 0) {
            $this->components->info('No due resident CSV imports were queued.');

            return self::SUCCESS;
        }

        $this->components->info("Queued {$result['queued_jobs']} job(s) for {$result['queued_sources']} CSV file(s).");

        return self::SUCCESS;
    }
}
