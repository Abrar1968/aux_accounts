<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\CustomerPayment;
use App\Models\Project;
use App\Services\System\BrandingService;
use App\Support\Money;

/**
 * Customer invoice (new). Bills the contract value of one receivable (project = base + change
 * orders, marketing = service revenue, opening = legacy balance, partnership = earned revenue) and
 * lists every receipt against it (cash + withheld fee, as in Payments!N). The status drives the
 * PAID / PARTIALLY PAID / UNPAID watermark on invoices and statements.
 */
class InvoiceService
{
    public const PAID = 'PAID';

    public const PARTIAL = 'PARTIALLY PAID';

    public const UNPAID = 'UNPAID';

    public function __construct(private readonly BrandingService $branding) {}

    public function invoice(string $type, int $id): array
    {
        $class = CustomerPaymentService::MODELS[$type] ?? throw new DomainException('Unknown receivable type.');
        $r = $class::query()->findOrFail($id);
        [$lines, $issued, $customer, $title] = match ($type) {
            'project' => [array_values(array_filter([
                ['description' => $r->name.' — contract value', 'detail' => trim(($r->revenue_method ?? '').($r->start_date ? ' · start '.$r->start_date->format('d M Y') : '')), 'amount' => (float) $r->base_value],
                (float) $r->change_orders != 0.0 ? ['description' => 'Approved change orders', 'detail' => '', 'amount' => (float) $r->change_orders] : null,
            ])), $r->start_date ?? $r->source_month, $r->customer ?: $r->name, $r->name],
            'marketing' => [[['description' => 'Marketing service — '.$r->service_month->format('F Y'), 'detail' => $r->notes ?? '', 'amount' => (float) $r->revenue]],
                $r->service_month, $r->client, 'Marketing service'],
            'opening' => [[['description' => 'Balance brought forward (before 1 Apr 2026)', 'detail' => $r->notes ?? '', 'amount' => (float) $r->opening_amount]],
                '2026-03-31', $r->customer ?? $r->code, 'Opening balance'],
            default => [[['description' => 'Partnership revenue — '.$r->earned_month->format('F Y'), 'detail' => '', 'amount' => (float) ($r->recognized_revenue ?? 0)]],
                $r->earned_month, $r->code, 'Partnership revenue'],
        };
        $payments = CustomerPayment::query()->where('receivable_type', $type)->where('receivable_id', $id)->orderBy('paid_at')->get()
            ->map(fn ($p) => ['code' => $p->code, 'date' => substr((string) $p->paid_at, 0, 10), 'method' => trim(($p->method ?? '').($p->account ? ' · '.$p->account : '')),
                'cash' => (float) $p->cash_received, 'fee' => (float) $p->processing_fee, 'amount' => Money::round((float) $p->cash_received + (float) $p->processing_fee)])->all();
        $total = Money::sum(array_column($lines, 'amount'));
        $paid = Money::sum(array_column($payments, 'amount'));

        return [
            'number' => 'INV-'.$r->code, 'reference' => $r->code, 'type' => $type, 'id' => $id, 'title' => $title,
            'issued' => substr((string) $issued, 0, 10), 'customer' => $customer, 'lines' => $lines, 'payments' => $payments,
            'total' => $total, 'paid' => $paid, 'balance' => Money::round($total - $paid),
            'status' => $this->status($total, $paid),
            'client_signature' => $r instanceof Project ? $this->branding->signatureUrls($r) : null,
        ];
    }

    /** Watermark + client signature for the running-balance customer statement. */
    public function statementExtras(string $type, int $id, array $statement): array
    {
        $billed = Money::round($statement['opening'] + $statement['total_debit']);
        $project = $type === 'project' ? Project::query()->find($id) : null;

        return [
            'status' => $this->status($billed, $statement['total_credit']),
            'client_signature' => $project ? $this->branding->signatureUrls($project) : null,
        ];
    }

    public function status(float $billed, float $paid): string
    {
        if ($paid > 0.005 && $paid >= $billed - 0.005) {
            return self::PAID;
        }

        return $paid > 0.005 ? self::PARTIAL : self::UNPAID;
    }
}
