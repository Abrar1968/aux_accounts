<?php

namespace App\Services\Shared;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    private bool $muted = false;

    /** Seeders mute auditing so imported history is not reported as user edits. */
    public function mute(callable $fn): mixed
    {
        $prev = $this->muted;
        $this->muted = true;
        try {
            return $fn();
        } finally {
            $this->muted = $prev;
        }
    }

    public function record(string $action, Model $model, ?array $before, ?array $after, ?string $reason = null): void
    {
        if ($this->muted) {
            return;
        }
        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => class_basename($model),
            'auditable_id' => $model->getKey(),
            'before' => $before,
            'after' => $after,
            'ip' => app()->runningInConsole() ? null : Request::ip(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
