<?php

namespace App\Console\Commands;

use App\Services\System\HealthCheckService;
use Illuminate\Console\Command;

class OpsCheck extends Command
{
    protected $signature = 'ops:check {--mail= : also send a test email to this address}';

    protected $description = 'Post-deploy smoke test: PHP, extensions, DB, data, balance check, storage, assets, Pusher, SMTP, scheduler';

    public function handle(HealthCheckService $health): int
    {
        $rows = $health->run($this->option('mail') ?: null);
        $this->table(['Check', 'Result', 'Detail'], array_map(fn ($r) => [$r[0], $r[1] ? '<info>OK</info>' : '<error>FAIL</error>', $r[2]], $rows));
        $failed = count(array_filter($rows, fn ($r) => ! $r[1]));
        $failed ? $this->warn("{$failed} check(s) need attention.") : $this->info('All checks passed — ready to use.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
