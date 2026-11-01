<?php

use Illuminate\Support\Facades\Broadcast;

// Single-tenant private channel for live ledger / CRM updates (Pusher).
Broadcast::channel('auxtech', fn ($user) => (bool) $user?->is_active);
Broadcast::channel('crm.user.{id}', fn ($user, $id) => (int) $user->id === (int) $id);
