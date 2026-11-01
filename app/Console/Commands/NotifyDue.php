<?php

namespace App\Console\Commands;

use App\Services\Notifications\ReminderService;
use App\Services\System\AutomationMonitorService;
use Illuminate\Console\Command;

class NotifyDue extends Command
{
    protected $signature = 'notify:due';

    protected $description = 'Follow-ups due, overdue receivables (Mondays) and unpaid / missing payroll';

    public function handle(AutomationMonitorService $monitor, ReminderService $reminders): int
    {
        $summary = $monitor->track('notify:due', fn () => [
            'followups' => $reminders->followUpsDue(),
            'receivables' => now()->isMonday() ? $reminders->receivablesOverdue() : 0,
            'payroll' => $reminders->payrollUnpaid(),
        ]);
        $this->info(json_encode($summary));

        return self::SUCCESS;
    }
}
