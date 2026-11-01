<?php

namespace App\Services\Accounting;

use App\Events\AutomationFinished;
use App\Notifications\ControlFailed;
use App\Services\Shared\PeriodService;
use App\Support\Period;

/**
 * Code.gs dailyAuxtechAutomation port (scheduled 02:10 Asia/Dhaka). Idempotent.
 */
class AccountingAutomationService
{
    public function __construct(
        private readonly PeriodService $periods,
        private readonly PartnershipService $partnerships,
        private readonly RevenueRecognitionService $recognition,
        private readonly ControlsService $controls,
        private readonly FinancialStatementService $statements,
    ) {
    }

    /** Idempotent: safe to run many times a day. Run tracking is done by AutomationMonitorService. */
    public function runDaily(?Period $current = null): array
    {
        $current ??= Period::current();
        $summary = [
            'periods_created' => $this->periods->ensureThrough($current),
            'partnership_rows' => $this->partnerships->generateThrough($current),
            'recognition' => $this->recognition->addProgressRows($current),
        ];
        $this->statements->flush();
        $controls = collect($this->controls->run($current));
        $failing = $controls->whereIn('status', ['REVIEW', 'INPUT REQUIRED'])->values();
        $summary['controls_failing'] = $failing->pluck('key')->all();
        if ($failing->isNotEmpty()) {
            app(\App\Services\Notifications\NotificationService::class)->send(new ControlFailed(
                $failing->count().' accounting control(s) need review',
                $failing->map(fn ($c) => $c['label'].' ('.$c['result'].')')->implode('; '),
                'warning', '/accounting/controls',
            ));
        }
        $balance = $controls->firstWhere('key', 'balance');
        if ($balance['status'] !== 'OK') {
            throw new \RuntimeException('Balance check is not zero: '.$balance['result']);
        }
        event(new AutomationFinished('accounting:daily', $summary));

        return $summary;
    }
}
