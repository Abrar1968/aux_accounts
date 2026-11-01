<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmReportService;
use App\Services\Notifications\ReminderService;
use App\Services\System\AutomationMonitorService;
use Illuminate\Console\Command;

class CrmSafetyCheck extends Command
{
    protected $signature = 'crm:safety-check';

    protected $description = 'Generate due CRM report snapshots (daily/monthly/yearly cursors) and meeting alerts';

    public function handle(AutomationMonitorService $monitor, CrmReportService $reports, ReminderService $reminders): int
    {
        $summary = $monitor->track('crm:safety-check', fn () => ['reports' => $reports->runCursors(), 'meeting_alerts' => $reminders->meetingsSoon()]);
        $this->info(json_encode($summary));

        return self::SUCCESS;
    }
}
