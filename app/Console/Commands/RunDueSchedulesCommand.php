<?php

namespace App\Console\Commands;

use App\Application\Services\SchedulerService;
use Illuminate\Console\Command;

class RunDueSchedulesCommand extends Command
{
    protected $signature = 'platform:schedules:run-due';

    protected $description = 'Dispatch platform jobs for due schedule definitions';

    public function handle(SchedulerService $scheduler): int
    {
        $count = $scheduler->runDue();
        $this->info("Dispatched {$count} scheduled job(s).");

        return self::SUCCESS;
    }
}
