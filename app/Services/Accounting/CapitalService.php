<?php

namespace App\Services\Accounting;

use App\Models\CapitalTransaction;
use App\Models\Investor;
use App\Repositories\Contracts\CapitalTransactionRepositoryInterface;
use App\Services\Shared\PeriodService;
use App\Support\Money;
use App\Support\Period;

/**
 * Investors + Capital_Ledger.
 */
class CapitalService
{
    public function __construct(
        private readonly CapitalTransactionRepositoryInterface $repo,
        private readonly PeriodService $periods,
    ) {}

    public function create(array $data): CapitalTransaction
    {
        $this->periods->assertOpen($data['txn_date']);

        return $this->repo->create($data + ['period' => Period::of($data['txn_date'])->date()]);
    }

    /** Cap table at a date: paid-in capital and effective ownership per investor. */
    public function capTable(?string $asOf = null): array
    {
        $asOf ??= now()->toDateString();

        return Investor::query()->with('ownerships')->orderBy('id')->get()->map(function (Investor $i) use ($asOf) {
            $own = $i->ownerships->filter(fn ($o) => $o->effective_from->toDateString() <= $asOf)->sortBy('effective_from')->last();

            return [
                'id' => $i->id,
                'name' => $i->name,
                'role' => $i->role_label,
                'is_equity' => $i->is_equity,
                'commitment' => (float) $i->capital_commitment,
                'ownership' => $own ? (float) $own->percent : 0.0,
                'paid_in' => Money::round($i->transactions()->where('txn_date', '<=', $asOf)->sum('equity_impact')),
            ];
        })->all();
    }
}
