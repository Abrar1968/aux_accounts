<?php

namespace App\Console\Commands;

use App\Models\AutomationRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Zero-command deploys: the scheduler (cron, every minute) applies new migrations shipped in an
 * update zip. It only runs `migrate --force` when a migration file is not yet in the migrations
 * table, so normal minutes cost one cheap query. Every run is written to the automation log.
 */
class AutoMigrate extends Command
{
    protected $signature = 'deploy:auto-migrate';

    protected $description = 'Apply pending migrations from a newly uploaded release (no-op when up to date)';

    public function handle(): int
    {
        $files = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));
        $ran = DB::table('migrations')->pluck('migration');
        $pending = $files->diff($ran)->values();
        if ($pending->isEmpty()) {
            return self::SUCCESS;
        }
        $started = now();
        try {
            Artisan::call('migrate', ['--force' => true]);
            AutomationRun::query()->create(['command' => 'deploy:auto-migrate', 'status' => 'ok', 'summary' => ['applied' => $pending->all()], 'started_at' => $started, 'finished_at' => now()]);
            $this->info('Applied: '.$pending->implode(', '));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            AutomationRun::query()->create(['command' => 'deploy:auto-migrate', 'status' => 'error', 'summary' => ['pending' => $pending->all()], 'error' => $e->getMessage(), 'started_at' => $started, 'finished_at' => now()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
