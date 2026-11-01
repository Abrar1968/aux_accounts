<?php

namespace App\Services\Notifications;

use App\Models\Lead;
use App\Models\OperatingExpense;
use App\Models\Payroll;
use App\Models\User;
use App\Notifications\DailyCrmDigest;
use App\Notifications\FollowUpsDue;
use App\Notifications\MeetingSoon;
use App\Notifications\PayrollUnpaid;
use App\Notifications\PeriodCloseReminder;
use App\Notifications\ReceivableOverdue;
use App\Services\Accounting\ControlsService;
use App\Services\Accounting\PayrollService;
use App\Services\Accounting\ReceivableService;
use App\Services\Crm\LeadComputer;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;

/**
 * Scheduled reminders (notify:due, crm:digest, notify:period-close, meeting alerts).
 */
class ReminderService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ReceivableService $receivables,
        private readonly PayrollService $payroll,
        private readonly ControlsService $controls,
    ) {
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'));
    }

    /** Leads due/overdue grouped by owner → one notification per owner. */
    public function followUpsDue(): int
    {
        $today = $this->now()->toDateString();
        $sent = 0;
        $groups = Lead::query()->whereNotIn('stage', LeadComputer::CLOSED_STAGES)->whereNotNull('next_followup')
            ->whereDate('next_followup', '<=', $today)->get()->groupBy('owner');
        foreach ($groups as $owner => $leads) {
            $user = User::query()->where('name', $owner)->where('is_active', true)->first();
            $users = $user ? collect([$user]) : $this->notifications->recipients(FollowUpsDue::TYPE);
            $overdue = $leads->filter(fn ($l) => $l->next_followup->toDateString() < $today)->count();
            $this->notifications->send(new FollowUpsDue(
                $leads->count().' follow-ups due today',
                "{$overdue} overdue, ".($leads->count() - $overdue).' due today'.($owner ? " for {$owner}" : '').'.',
                $overdue ? 'warning' : 'info', '/crm/follow-ups', ['count' => $leads->count(), 'overdue' => $overdue],
            ), $users);
            $sent++;
        }

        return $sent;
    }

    public function meetingsSoon(): int
    {
        $now = $this->now();
        $sent = 0;
        $leads = Lead::query()->whereNotIn('stage', LeadComputer::CLOSED_STAGES)->whereNotNull('meeting_at')
            ->whereBetween('meeting_at', [$now->addMinutes(55), $now->addMinutes(60)])->get();
        foreach ($leads as $l) {
            $user = User::query()->where('name', $l->owner)->first();
            $this->notifications->send(new MeetingSoon('Meeting in 1 hour', $l->phone.' · '.($l->meeting_details ?: $l->outcome), 'info', '/crm/leads/'.$l->id), $user ?? $this->notifications->recipients(MeetingSoon::TYPE));
            $sent++;
        }

        return $sent;
    }

    public function receivablesOverdue(int $days = 30): int
    {
        $rows = $this->receivables->aging($this->now()->toDateString())->where('days', '>', $days);
        if ($rows->isEmpty()) {
            return 0;
        }
        $total = Money::sum($rows->pluck('ar'));
        $this->notifications->send(new ReceivableOverdue(
            $rows->count().' receivables older than '.$days.' days',
            'Outstanding ৳'.number_format($total, 2).'. Largest: '.$rows->sortByDesc('ar')->first()['name'].'.',
            'warning', '/accounting/receivables',
            ['lines' => $rows->take(10)->map(fn ($r) => $r['name'].' — ৳'.number_format($r['ar'], 2).' ('.$r['days'].' days)')->all()],
        ));

        return $rows->count();
    }

    /** Unpaid payroll after the 7th of the following month + employees without a payroll row. */
    public function payrollUnpaid(): int
    {
        $now = $this->now();
        $month = Period::of($now)->previous();
        if ($now->day < 7) {
            return 0;
        }
        $unpaid = Payroll::query()->with('employee')->where('period', $month->date())->get()
            ->filter(fn ($p) => $this->payroll->summary($p)['unpaid'] > 0.01);
        $missing = collect($this->controls->run(Period::of($now)))->firstWhere('key', 'missing_payroll');
        if ($unpaid->isEmpty() && ($missing['result'] ?? 0) === 0) {
            return 0;
        }
        $lines = $unpaid->map(fn ($p) => $p->employee->name.' — ৳'.number_format($this->payroll->summary($p)['unpaid'], 2).' unpaid')->values()->all();
        if (($missing['result'] ?? 0) > 0) {
            $lines[] = 'Missing payroll: '.$missing['note'];
        }
        $this->notifications->send(new PayrollUnpaid('Payroll needs attention for '.$month->label(), count($lines).' item(s) to review.', 'warning', '/accounting/payroll?month='.$month->key(), ['lines' => $lines]));

        return count($lines);
    }

    public function periodCloseChecklist(): array
    {
        $p = Period::of($this->now());
        $failing = collect($this->controls->run($p))->where('status', '!=', 'OK');
        $unpaid = OperatingExpense::query()->where('accrual_month', $p->date())->with('payments')->get()
            ->filter(fn ($e) => (float) $e->amount - (float) $e->payments->sum('amount') > 0.01)->count();
        $lines = array_merge(
            ["{$unpaid} unpaid bills this month"],
            $failing->map(fn ($c) => $c['label'].': '.$c['status'])->values()->all(),
            ['Review receivables, payroll and recognition, then close '.$p->label().'.'],
        );
        $this->notifications->send(new PeriodCloseReminder('Month-end close: '.$p->label(), count($lines).' checklist items', 'info', '/accounting/controls', ['lines' => $lines]));

        return $lines;
    }

    public function crmDigest(): int
    {
        $today = $this->now()->toDateString();
        $sent = 0;
        foreach (User::query()->where('is_active', true)->whereHas('role', fn ($q) => $q->whereIn('name', ['caller', 'sales_manager']))->get() as $u) {
            $leads = Lead::query()->where('owner', $u->name)->whereNotIn('stage', LeadComputer::CLOSED_STAGES)
                ->whereNotNull('next_followup')->whereDate('next_followup', '<=', $today)->orderBy('next_followup')->get();
            if ($leads->isEmpty()) {
                continue;
            }
            $this->notifications->send(new DailyCrmDigest('Your follow-ups for '.$today, $leads->count().' leads need a call today.', 'info', '/crm/follow-ups',
                ['lines' => $leads->take(25)->map(fn ($l) => $l->phone.' — '.$l->outcome.' (due '.$l->next_followup->format('d M').')')->all()]), $u);
            $sent++;
        }

        return $sent;
    }
}
