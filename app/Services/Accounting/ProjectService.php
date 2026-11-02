<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Services\Shared\AuditService;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SequenceService;
use App\Services\System\BrandingService;
use App\Support\Money;
use App\Support\Period;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Projects / International_Projects registers.
 */
class ProjectService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repo,
        private readonly SequenceService $sequences,
        private readonly ReferralService $referrals,
        private readonly RevenueRecognitionService $recognition,
        private readonly PeriodService $periods,
        private readonly BrandingService $branding,
    ) {}

    public function create(array $data): Project
    {
        $signature = $data['client_signature'] ?? null; // optional image, stored after the row exists
        unset($data['client_signature']);
        $data = $this->normaliseReferrers($data);

        return DB::transaction(function () use ($data, $signature) {
            $kind = $data['kind'] ?? 'project';
            $start = $data['start_date'] ?? now()->toDateString();
            $prefix = match ($kind) {
                'international' => 'IPRJ', 'lead' => 'LEAD', default => 'PRJ'
            };
            $n = $this->sequences->next($prefix);
            $project = $this->repo->create(array_merge([
                'revenue_method' => $kind === 'lead' ? 'Lead / No Revenue' : 'Point in Time',
                'change_orders' => 0,
            ], $data, [
                'code' => $prefix.'-'.Period::of($start)->start->format('ym').'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'kind' => $kind,
                'customer' => $data['customer'] ?? $data['name'],
                'source_month' => Period::of($start)->date(),
            ]));
            $this->referrals->syncForProject($project);
            if ($signature instanceof UploadedFile) {
                $this->branding->setClientSignature($project, $signature);
            }

            return $project;
        });
    }

    public function update(Project $project, array $data): Project
    {
        if (isset($data['actual_delivery_date'])) {
            $this->periods->assertOpen($data['actual_delivery_date']);
        }
        $project = DB::transaction(function () use ($project, $data) {
            $project = $this->repo->update($project, $this->normaliseReferrers($data));
            $this->referrals->syncForProject($project); // may refuse removing an already-paid referrer

            return $project;
        });
        $this->recognition->recalculateProject($project);

        return $project;
    }

    /** "Rizwan & abrar ,Rizwan" → "Rizwan, abrar" (one canonical list; null when empty). */
    private function normaliseReferrers(array $data): array
    {
        if (array_key_exists('referrer', $data)) {
            $data['referrer'] = implode(', ', Project::parseReferrers($data['referrer'])) ?: null;
        }

        return $data;
    }

    /** Projects!O:AC derived columns. */
    public function metrics(Project $p): array
    {
        $payments = $p->payments()->get();
        $cash = Money::sum($payments->pluck('cash_received'));
        $fees = Money::sum($payments->pluck('processing_fee'));
        $gross = Money::round($cash + $fees);
        $total = $p->total_value;
        $recRows = $p->recognitions()->get();
        $recognized = ($p->revenue_method === 'Over Time' || $recRows->isNotEmpty())
            ? Money::sum($recRows->pluck('recognized_amount'))
            : (($p->actual_delivery_date && ! $p->isLead()) ? $total : 0.0);
        $lastPayment = $payments->max('paid_at');

        $paymentStatus = match (true) {
            $p->isLead() => 'Not applicable',
            $total <= 0 => 'Value needed',
            Money::round($gross - $total) > 0 => 'Overpaid',
            Money::round($gross - $total) == 0 => 'Paid',
            $gross > 0 => 'Partially Paid',
            default => 'Unpaid',
        };
        $workStatus = match (true) {
            $p->isLead() => 'Lead / Unconverted',
            $p->actual_delivery_date !== null => 'Delivered',
            $p->start_date !== null => 'In progress',
            default => 'Delivery not dated',
        };
        $progress = $p->revenue_method === 'Over Time'
            ? (float) ($recRows->where('entry_check', 'OK')->max('ending_progress') ?? 0)
            : ($p->actual_delivery_date ? 1.0 : 0.0);

        return [
            'total_value' => $total,
            'referral_memo' => $p->referrer ? $this->referrals->memoFor($total) : null,
            'progress' => $progress,
            'payment_status' => $paymentStatus,
            'cash_collected' => $cash,
            'processing_fees' => $fees,
            'gross_settlement' => $gross,
            'revenue_recognized' => $recognized,
            'current_ar' => max(0.0, Money::round($recognized - $gross)),
            'customer_advance' => max(0.0, Money::round($gross - $recognized)),
            'last_payment' => $lastPayment?->toDateTimeString(),
            'fully_paid_date' => ($total > 0 && $gross >= $total) ? $lastPayment?->toDateTimeString() : null,
            'collection_percent' => $total > 0 ? min(1, $gross / $total) : null,
            'work_status' => $workStatus,
        ];
    }

    /**
     * Delete a project registered by mistake. Refused when money or history depends on it:
     * customer receipts, historical (workbook) recognition, or a referral already paid.
     * Auto / manual recognition rows in open periods and unpaid referral rows go with it.
     */
    public function delete(Project $project, string $reason): void
    {
        $this->periods->assertOpen($project->source_month);
        if ($n = $project->payments()->count()) {
            throw new DomainException("{$project->code} has {$n} customer receipt(s). Delete those receipts first.");
        }
        if ($project->recognitions()->where('is_historical', true)->exists()) {
            throw new DomainException("{$project->code} has historical recognised revenue from the workbook and cannot be deleted.");
        }
        if ($project->referrals()->where('paid_amount', '>', 0)->exists()) {
            throw new DomainException("A referral on {$project->code} is already paid. Reverse the referral payment first.");
        }
        DB::transaction(function () use ($project, $reason) {
            foreach ($project->recognitions()->get() as $r) {
                $this->periods->assertOpen($r->period);
                $r->delete();
            }
            $project->referrals()->delete();
            $this->branding->setClientSignature($project, null);
            app(AuditService::class)->withReason($reason, fn () => $project->delete());
        });
    }
}
