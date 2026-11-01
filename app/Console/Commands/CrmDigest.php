<?php

namespace App\Console\Commands;

use App\Services\Notifications\ReminderService;
use App\Services\System\AutomationMonitorService;
use Illuminate\Console\Command;

class CrmDigest extends Command
{
    protected $signature = 'crm:digest';

    protected $description = 'Email each caller their follow-ups for today';

    public function handle(AutomationMonitorService $monitor, ReminderService $reminders): int
    {
        $this->info(json_encode($monitor->track('crm:digest', fn () => ['sent' => $reminders->crmDigest()])));

        return self::SUCCESS;
    }
}
