<?php

namespace App\Console\Commands;

use App\Services\Accounting\AccountingAutomationService;
use App\Services\System\AutomationMonitorService;
use App\Support\Period;
use Illuminate\Console\Command;

class AccountingDaily extends Command
{
    protected $signature = 'accounting:daily {--month= : YYYY-MM, defaults to the current Dhaka month}';

    protected $description = 'Create missing periods, partnership rows, recognition rows and run accounting controls';

    public function handle(AutomationMonitorService $monitor, AccountingAutomationService $automation): int
    {
        $month = $this->option('month') ? Period::of($this->option('month')) : null;
        $summary = $monitor->track('accounting:daily', fn () => $automation->runDaily($month));
        $this->info(json_encode($summary));

        return self::SUCCESS;
    }
}
