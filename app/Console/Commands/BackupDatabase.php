<?php

namespace App\Console\Commands;

use App\Services\System\AutomationMonitorService;
use App\Services\System\BackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=30 : days to keep}';

    protected $description = 'Write a gzipped SQL backup to storage/app/backups';

    public function handle(AutomationMonitorService $monitor, BackupService $backup): int
    {
        $path = $monitor->track('backup:database', fn () => ['path' => $backup->run((int) $this->option('keep'))]);
        $this->info('Backup written: '.$path['path']);

        return self::SUCCESS;
    }
}
