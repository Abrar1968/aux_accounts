<?php

namespace App\Services\Accounting;

use App\Models\MarketingService;
use App\Repositories\Contracts\MarketingServiceRepositoryInterface;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SequenceService;
use App\Support\Money;
use App\Support\Period;

/** Marketing_Services: recurring service revenue, earned in the service month. */
class MarketingRevenueService
{
    public function __construct(
        private readonly MarketingServiceRepositoryInterface $repo,
        private readonly PeriodService $periods,
        private readonly SequenceService $sequences,
    ) {
    }

    public function create(array $data): MarketingService
    {
        $month = Period::of($data['service_month']);
        $this->periods->assertOpen($month->date());

        return $this->repo->create([
            'code' => 'MKT-'.$month->start->format('ym').'-'.str_pad((string) $this->sequences->next('MKT'), 3, '0', STR_PAD_LEFT),
            'service_month' => $month->date(), 'client' => $data['client'], 'revenue' => Money::round($data['revenue']),
            'notes' => $data['notes'] ?? null, 'source' => 'Direct Entry',
        ]);
    }

    public function update(MarketingService $m, array $data): MarketingService
    {
        $this->periods->assertOpen($m->service_month, $data['service_month'] ?? null);

        return $this->repo->update($m, $data);
    }

    /** Marketing_Services!E:I */
    public function status(MarketingService $m): array
    {
        $payments = $m->payments()->get();
        $gross = Money::sum($payments->map(fn ($p) => (float) $p->cash_received + (float) $p->processing_fee));
        $ar = max(0.0, Money::round((float) $m->revenue - $gross));

        return [
            'cash_received' => Money::sum($payments->pluck('cash_received')),
            'payment_date' => $payments->max('paid_at')?->toDateTimeString(),
            'accounts_receivable' => $ar,
            'customer_advance' => max(0.0, Money::round($gross - (float) $m->revenue)),
            'status' => $ar > 0 ? 'Open' : ($gross > 0 ? 'Paid' : 'Unpaid'),
        ];
    }
}
