<?php

namespace App\Services\Accounting;

use App\Events\PaymentPosted;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\IdempotencyConflictException;
use App\Models\CustomerPayment;
use App\Models\MarketingService;
use App\Models\OpeningReceivable;
use App\Models\PartnershipRevenue;
use App\Models\Project;
use App\Repositories\Contracts\CustomerPaymentRepositoryInterface;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SequenceService;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Payments tab: customer receipts only (cash really received). Processing fees are expensed
 * automatically by the statement engine; they must never be re-entered as an operating expense.
 */
class CustomerPaymentService
{
    public const MODELS = [
        'project' => Project::class,
        'marketing' => MarketingService::class,
        'opening' => OpeningReceivable::class,
        'partnership' => PartnershipRevenue::class,
    ];

    public function __construct(
        private readonly CustomerPaymentRepositoryInterface $repo,
        private readonly SequenceService $sequences,
        private readonly PeriodService $periods,
    ) {
    }

    /** @param array{paid_at:string, receivable_type:string, receivable_id:int, cash_received:float, processing_fee?:?float, method?:?string, account?:?string, notes?:?string, request_id?:?string, source_name?:?string} $data */
    public function record(array $data): CustomerPayment
    {
        $paidAt = CarbonImmutable::parse($data['paid_at'], config('app.timezone'));
        if ($paidAt->isAfter(now()->endOfDay())) {
            throw new DomainException('Payment date cannot be in the future.');
        }
        $this->periods->assertOpen($paidAt->toDateString());
        $class = self::MODELS[$data['receivable_type']] ?? throw new DomainException('Unknown receivable type.');
        $target = $class::query()->findOrFail($data['receivable_id']);
        $cash = Money::round($data['cash_received']);
        $fee = Money::round($data['processing_fee'] ?? 0);
        if ($cash <= 0 || $fee < 0 || ! Money::hasAtMostTwoDecimals((float) $data['cash_received'])) {
            throw new DomainException('Cash received must be positive with at most two decimals; fee cannot be negative.');
        }

        if (! empty($data['request_id'])) {
            $existing = $this->repo->query()->where('request_id', $data['request_id'])->first();
            if ($existing) {
                $same = $existing->receivable_type === $data['receivable_type'] && (int) $existing->receivable_id === (int) $data['receivable_id']
                    && Money::paisa($existing->cash_received) === Money::paisa($cash) && Money::paisa($existing->processing_fee) === Money::paisa($fee);
                if (! $same) {
                    throw new IdempotencyConflictException('This request was saved with different details.');
                }

                return $existing;
            }
        }

        return DB::transaction(function () use ($data, $paidAt, $target, $cash, $fee) {
            $n = $this->sequences->next('PAY');
            $name = $target->name ?? $target->client ?? $target->customer ?? $target->code;
            $payment = $this->repo->create([
                'code' => 'PAY-'.$paidAt->format('ymd').'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'paid_at' => $paidAt->toDateTimeString(),
                'period' => Period::of($paidAt)->date(),
                'receivable_type' => $data['receivable_type'],
                'receivable_id' => $target->id,
                'revenue_code' => $target->code,
                'source_name' => $data['source_name'] ?? $name,
                'canonical_name' => $name,
                'cash_received' => $cash,
                'processing_fee' => $fee,
                'method' => $data['method'] ?? null,
                'account' => $data['account'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source_workbook' => 'Direct Entry',
                'request_id' => $data['request_id'] ?? null,
                'posted_by' => Auth::id(),
            ]);
            event(new PaymentPosted('customer', $payment->code, $cash));

            return $payment;
        });
    }

    public function revenueType(CustomerPayment $p): string
    {
        return match ($p->receivable_type) {
            'opening' => 'Legacy A/R',
            'marketing' => 'Marketing Service',
            'partnership' => 'Partnership',
            default => 'Project',
        };
    }

    public function delete(CustomerPayment $p, string $reason): void
    {
        $this->periods->assertOpen($p->paid_at);
        app(\App\Services\Shared\AuditService::class)->record('deleted', $p, $p->toArray(), null, $reason);
        $p->delete();
    }
}
