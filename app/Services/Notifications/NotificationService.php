<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Notification catalogue, per-user channel preferences and fan-out (docs/07 §3–4).
 */
class NotificationService
{
    /** type => [label, default channels, roles that receive it, locked (cannot disable in-app)] */
    public const CATALOGUE = [
        'payment_recorded' => ['Payment recorded', ['in_app', 'push'], ['owner', 'accountant'], false],
        'posting_rejected' => ['Posting rejected', ['in_app', 'push'], [], false],
        'followups_due' => ['Follow-ups due', ['in_app', 'push', 'email'], ['owner', 'sales_manager', 'caller'], false],
        'meeting_soon' => ['Meeting soon', ['in_app', 'push'], ['owner', 'sales_manager', 'caller'], false],
        'receivable_overdue' => ['Receivable overdue', ['in_app', 'email'], ['owner', 'accountant'], false],
        'payroll_unpaid' => ['Payroll unpaid / missing', ['in_app', 'email'], ['owner', 'accountant'], false],
        'period_close_reminder' => ['Month-end close checklist', ['in_app', 'email'], ['owner', 'accountant'], false],
        'control_failed' => ['Accounting control needs review', ['in_app', 'push'], ['owner', 'accountant'], false],
        'automation_failed' => ['Automation failed', ['in_app', 'push', 'email'], ['owner'], true],
        'lead_assigned' => ['Lead assigned to you', ['in_app', 'push'], [], false],
        'deal_won' => ['Deal won', ['in_app', 'push'], ['owner', 'sales_manager'], false],
        'daily_crm_digest' => ['Daily CRM digest', ['email'], ['caller', 'sales_manager'], false],
        'crm_report_ready' => ['CRM report ready', ['in_app'], ['owner', 'sales_manager'], false],
    ];

    private const CHANNEL_MAP = ['in_app' => 'database', 'push' => 'broadcast', 'email' => 'mail'];

    /** Laravel channel names for a user + type, honouring preferences. */
    public function channelsFor(object $notifiable, string $type): array
    {
        $def = self::CATALOGUE[$type] ?? ['', ['in_app'], [], false];
        $pref = $notifiable instanceof User
            ? NotificationPreference::query()->where('user_id', $notifiable->id)->where('type', $type)->first()
            : null;
        $on = $pref
            ? array_keys(array_filter(['in_app' => $pref->in_app, 'push' => $pref->push, 'email' => $pref->email]))
            : $def[1];
        if ($def[3] && ! in_array('in_app', $on, true)) {
            $on[] = 'in_app';
        }
        if (in_array('push', $on, true) && ! in_array('in_app', $on, true)) {
            $on[] = 'in_app'; // realtime needs the stored notification to deep-link
        }
        if ($notifiable instanceof User && ! $notifiable->email) {
            $on = array_diff($on, ['email']);
        }

        return array_values(array_unique(array_map(fn ($c) => self::CHANNEL_MAP[$c], $on)));
    }

    /** @return Collection<int, User> active users whose role receives this type */
    public function recipients(string $type): Collection
    {
        $roles = self::CATALOGUE[$type][2] ?? [];

        return User::query()->where('is_active', true)->whereHas('role', fn ($q) => $q->whereIn('name', $roles))->get();
    }

    public function send(AppNotification $notification, iterable|User|null $users = null): void
    {
        $users ??= $this->recipients($notification::TYPE);
        Notification::send($users instanceof User ? [$users] : $users, $notification);
    }

    /** @return list<array{type:string,label:string,in_app:bool,push:bool,email:bool,locked:bool}> */
    public function preferencesFor(User $user): array
    {
        $saved = NotificationPreference::query()->where('user_id', $user->id)->get()->keyBy('type');

        return collect(self::CATALOGUE)->map(function ($def, $type) use ($saved) {
            $p = $saved[$type] ?? null;

            return [
                'type' => $type, 'label' => $def[0], 'locked' => $def[3],
                'in_app' => $p ? (bool) $p->in_app : in_array('in_app', $def[1], true),
                'push' => $p ? (bool) $p->push : in_array('push', $def[1], true),
                'email' => $p ? (bool) $p->email : in_array('email', $def[1], true),
            ];
        })->values()->all();
    }

    /** @param list<array{type:string,in_app?:bool,push?:bool,email?:bool}> $prefs */
    public function savePreferences(User $user, array $prefs): void
    {
        foreach ($prefs as $p) {
            if (! isset(self::CATALOGUE[$p['type']])) {
                continue;
            }
            NotificationPreference::query()->updateOrCreate(['user_id' => $user->id, 'type' => $p['type']], [
                'in_app' => self::CATALOGUE[$p['type']][3] ? true : (bool) ($p['in_app'] ?? false),
                'push' => (bool) ($p['push'] ?? false),
                'email' => (bool) ($p['email'] ?? false),
            ]);
        }
    }

    public function list(User $user, string $filter = 'all', int $perPage = 20)
    {
        $q = $user->notifications();
        if ($filter === 'unread') {
            $q = $user->unreadNotifications();
        }

        return $q->paginate($perPage)->withQueryString();
    }

    public function markRead(User $user, ?string $id = null): void
    {
        $id ? $user->unreadNotifications()->whereKey($id)->update(['read_at' => now()])
            : $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
