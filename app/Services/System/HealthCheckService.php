<?php

namespace App\Services\System;

use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Shared\PeriodService;
use App\Support\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/** Post-deploy smoke test (php artisan ops:check). Each check: [label, ok, detail]. */
class HealthCheckService
{
    public function __construct(
        private readonly FinancialStatementService $statements,
        private readonly PeriodService $periods,
    ) {}

    /** @return list<array{0:string,1:bool,2:string}> */
    public function run(?string $mailTo = null): array
    {
        $checks = [];
        $add = function (string $label, callable $fn) use (&$checks) {
            try {
                [$ok, $detail] = $fn();
            } catch (\Throwable $e) {
                [$ok, $detail] = [false, $e->getMessage()];
            }
            $checks[] = [$label, (bool) $ok, (string) $detail];
        };

        $add('PHP version ≥ 8.2', fn () => [version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION]);
        foreach (['gd', 'pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'zlib'] as $ext) {
            $add("PHP extension {$ext}", fn () => [extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing — enable it in cPanel → Select PHP Version']);
        }
        $add('APP_KEY set', fn () => [(string) config('app.key') !== '', config('app.key') ? 'ok' : 'run php artisan key:generate']);
        $add('Debug off in production', fn () => [! (config('app.env') === 'production' && config('app.debug')), 'APP_ENV='.config('app.env').', APP_DEBUG='.(config('app.debug') ? 'true' : 'false')]);
        $add('Database connection', fn () => [DB::connection()->getPdo() !== null, DB::connection()->getDatabaseName()]);
        $add('Migrations applied', fn () => [DB::table('migrations')->count() >= 11, DB::table('migrations')->count().' migrations']);
        $add('Seed data present', fn () => [Project::query()->count() >= 18 && Lead::query()->count() >= 142, Project::query()->count().' projects, '.Lead::query()->count().' leads, '.User::query()->count().' users']);
        $add('Balance check = 0 (every month)', function () {
            $bad = [];
            foreach (Period::range($this->periods->modelStart(), Period::current()) as $p) {
                $v = $this->statements->forPeriod($p)['balance_check'];
                if (abs($v) > 0.005) {
                    $bad[] = $p->label().' '.number_format($v, 2);
                }
            }

            return [! $bad, $bad ? implode('; ', $bad) : 'all months balance'];
        });
        $add('Storage writable', function () {
            Storage::disk('local')->put('health.txt', 'ok');
            Storage::disk('local')->delete('health.txt');

            return [is_writable(storage_path('framework/sessions')) && is_writable(base_path('bootstrap/cache')), 'storage/ + bootstrap/cache'];
        });
        $add('Assets built', fn () => [is_file(public_path('build/manifest.json')), 'public/build/manifest.json']);
        $add('Pusher configured', fn () => [config('broadcasting.default') === 'pusher' && (string) config('broadcasting.connections.pusher.secret') !== '', 'app '.config('broadcasting.connections.pusher.app_id').' / '.config('broadcasting.connections.pusher.options.cluster')]);
        $add('Mail configured', fn () => [config('mail.default') === 'smtp' && (string) config('mail.mailers.smtp.password') !== '', config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port').' as '.config('mail.mailers.smtp.username')]);
        $add('Scheduler heartbeat', function () {
            // crm:safety-check runs every 5 minutes once the cron line is active (seeded Apps Script history does not count)
            $last = DB::table('automation_runs')->where('command', 'crm:safety-check')->max('started_at');
            $fresh = $last !== null && $last >= now()->subMinutes(10)->toDateTimeString();

            return [$fresh, $last ? 'crm:safety-check last ran '.$last.($fresh ? '' : ' — older than 10 min, check the cron line') : 'not run yet — add the cron line, then wait 5 minutes'];
        });
        if ($mailTo) {
            $add("Test email to {$mailTo}", function () use ($mailTo) {
                Mail::raw('Auxtech Accounts test email — SMTP is working ('.now()->toDateTimeString().').', fn ($m) => $m->to($mailTo)->subject('Auxtech Accounts test email'));

                return [true, 'sent'];
            });
        }

        return $checks;
    }
}
