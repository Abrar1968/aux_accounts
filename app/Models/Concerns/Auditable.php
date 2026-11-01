<?php

namespace App\Models\Concerns;

use App\Services\Shared\AuditService;

/**
 * Records every create / update / delete in audit_logs (replaces sheet Created At / Updated At).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => app(AuditService::class)->record('created', $m, null, $m->getAttributes()));
        static::updated(function ($m) {
            $changes = $m->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $before = array_intersect_key($m->getOriginal(), $changes);
            app(AuditService::class)->record('updated', $m, $before, $changes);
        });
        static::deleted(fn ($m) => app(AuditService::class)->record('deleted', $m, $m->getOriginal(), null));
    }
}
