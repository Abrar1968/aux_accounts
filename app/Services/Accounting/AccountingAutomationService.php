<?php

namespace App\Services\Accounting;

use App\Events\AutomationFinished;
use App\Models\AutomationRun;
use App\Services\Shared\PeriodService;
use App\Support\Period;
use Throwable;

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

    public function runDaily(?Period $current = null): array
    {
        $current ??= Period::current();
        $run = AutomationRun::query()->create(['command' => 'accounting:daily', 'status' => 'running', 'started_at' => now()]);
        try {
            $summary = [
                'periods_created' => $this->periods->ensureThrough($current),
                'partnership_rows' => $this->partnerships->generateThrough($current),
                'recognition' => $this->recognition->addProgressRows($current),
            ];
            $this->statements->flush();
            $controls = $this->controls->run($current);
            $summary['controls_failing'] = collect($controls)->where('status', '!=', 'OK')->pluck('key')->values()->all();
            $balance = collect($controls)->firstWhere('key', 'balance');
            if ($balance['status'] !== 'OK') {
                throw new \RuntimeException('Balance check is not zero: '.$balance['result']);
            }
            $run->update(['status' => 'ok', 'summary' => $summary, 'finished_at' => now()]);
            event(new AutomationFinished('accounting:daily', $summary));

            return $summary;
        } catch (Throwable $e) {
            $run->update(['status' => 'error', 'error' => $e->getMessage(), 'finished_at' => now()]);
            throw $e;
        }
    }
}
