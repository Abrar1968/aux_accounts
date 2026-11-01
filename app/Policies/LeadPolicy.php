<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/** Callers work their own leads; managers / owners see all (crm.leads.view_all or crm.*). */
class LeadPolicy
{
    private function all(User $u): bool
    {
        return $u->hasPermission('crm.leads.view_all');
    }

    private function own(User $u, Lead $l): bool
    {
        return $l->owner_id === $u->id || mb_strtolower((string) $l->owner) === mb_strtolower($u->name);
    }

    public function view(User $u, Lead $l): bool
    {
        return $u->hasPermission('crm.leads.view') && ($this->all($u) || $this->own($u, $l));
    }

    public function update(User $u, Lead $l): bool
    {
        return $u->hasPermission('crm.leads.update') && ($this->all($u) || $this->own($u, $l));
    }

    public function delete(User $u, Lead $l): bool
    {
        return $u->hasPermission('crm.leads.delete');
    }
}
