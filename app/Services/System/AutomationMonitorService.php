<?php

namespace App\Services\System;

use App\Models\AuditLog;
use App\Models\AutomationRun;
use App\Models\User;
use App\Notifications\AutomationFailed;
use App\Services\Notifications\NotificationService;
use Closure;
use Throwable;

/** Automation runs (replaces the Apps_Script status tab + CRM Automation Log) and audit browsing. */
class AutomationMonitorService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /** Runs a scheduled job, records it, and alerts owners on failure. */
    public function track(string $command, Closure $job): mixed
    {
        $run = AutomationRun::query()->create(['command' => $command, 'status' => 'running', 'started_at' => now()]);
        try {
            $summary = $job();
            $run->update(['status' => 'ok', 'summary' => is_array($summary) ? $summary : ['result' => $summary], 'finished_at' => now()]);

            return $summary;
        } catch (Throwable $e) {
            $run->update(['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
            $this->notifications->send(new AutomationFailed('Automation failed: '.$command, $e->getMessage(), 'error', '/system/automation'));
            throw $e;
        }
    }

    public function overview(): array
    {
        $last = fn (string $cmd) => AutomationRun::query()->where('command', $cmd)->where('status', 'ok')->latest('started_at')->first()?->started_at;

        return [
            'health' => collect(['accounting:daily', 'crm:safety-check', 'notify:due', 'crm:digest', 'backup:database'])
                ->map(fn ($c) => ['command' => $c, 'last_success' => $last($c)?->toDateTimeString(),
                    'stale' => ! $last($c) || $last($c)->lt(now()->subDays(2))])->all(),
            'runs' => AutomationRun::query()->latest('started_at')->paginate(30)->withQueryString(),
        ];
    }

    public function audit(array $filters)
    {
        return AuditLog::query()->with('user:id,name')
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('auditable_type', $t))
            ->when($filters['user_id'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->latest('id')->paginate(40)->withQueryString();
    }
}
