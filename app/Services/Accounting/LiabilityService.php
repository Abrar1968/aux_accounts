<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\Liability;
use App\Repositories\Contracts\LiabilityRepositoryInterface;
use App\Repositories\Contracts\RevenueRecognitionRepositoryInterface;
use App\Services\Shared\PeriodService;
use App\Support\Money;
use App\Support\Period;
use Illuminate\Support\Collection;

/**
 * Liabilities ledger + Siam_Contract tracker.
 */
class LiabilityService
{
    public const TYPES = [
        'LIA-DEF' => 'Prior Company Dues',
        'LIA-AC' => 'Asset Financing',
        'LIA-SIAM' => 'Deferred Contract / Revenue Share',
        'LIA-SIAM-SHARE' => 'Siam Revenue Share Payable',
    ];

    public function __construct(
        private readonly LiabilityRepositoryInterface $repo,
        private readonly RevenueRecognitionRepositoryInterface $recognitions,
        private readonly PeriodService $periods,
    ) {}

    public function create(array $data): Liability
    {
        $this->periods->assertOpen($data['txn_date']);
        if ((float) ($data['increase'] ?? 0) < 0 || (float) ($data['decrease'] ?? 0) < 0) {
            throw new DomainException('Increase and decrease must be non-negative; use cash impact for direction.');
        }

        return $this->repo->create($data + [
            'period' => Period::of($data['txn_date'])->date(),
            'liability_type' => self::TYPES[$data['liability_code']] ?? 'Other',
        ]);
    }

    /** Running balance per liability (Liabilities!L). */
    public function ledger(): Collection
    {
        $running = [];

        return $this->repo->query()->orderBy('txn_date')->orderBy('id')->get()->map(function (Liability $l) use (&$running) {
            $code = $l->liability_code;
            if ($code === 'LIA-SIAM-SHARE') {
                $accrued = $this->recognitions->sumBefore('siam_share', 'period', Period::of($l->period)->cut());
                $running[$code] = ($running[$code] ?? 0) + (float) $l->decrease;
                $balance = max(0, Money::round($accrued - $running[$code]));
            } else {
                $running[$code] = Money::round(($running[$code] ?? 0) + (float) $l->increase - (float) $l->decrease);
                $balance = $running[$code];
            }

            return $l->toArray() + ['balance' => $balance];
        });
    }

    public function siamTracker(): array
    {
        $funding = Money::round($this->repo->query()->where('liability_code', 'LIA-SIAM')->sum('increase'));

        return [
            'commitment' => 500000.0,
            'funding_received' => $funding,
            'eligible_revenue' => Money::round($this->recognitions->query()->sum('siam_eligible')),
            'share_accrued' => Money::round($this->recognitions->query()->sum('siam_share')),
            'share_paid' => Money::round($this->repo->query()->where('liability_code', 'LIA-SIAM-SHARE')->sum('decrease')),
        ];
    }
}
