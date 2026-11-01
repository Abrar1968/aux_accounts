<?php

namespace App\Console\Commands;

use App\Services\Notifications\ReminderService;
use App\Services\System\AutomationMonitorService;
use Illuminate\Console\Command;

class NotifyPeriodClose extends Command
{
    protected $signature = 'notify:period-close';

    protected $description = 'Send the month-end close checklist';

    public function handle(AutomationMonitorService $monitor, ReminderService $reminders): int
    {
        $this->info(json_encode($monitor->track('notify:period-close', fn () => ['lines' => $reminders->periodCloseChecklist()])));

        return self::SUCCESS;
    }
}
