<?php

namespace App\Services\Accounting;

use App\Events\PaymentPosted;
use App\Exceptions\Domain\IdempotencyConflictException;
use App\Exceptions\Domain\PostingRejectedException;
use App\Models\EmployeeAdvance;
use App\Models\ExpensePayment;
use App\Models\OperatingExpense;
use App\Models\Payroll;
use App\Models\SalaryPayment;
use App\Services\Shared\PeriodService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Port of AuxtechPayments.gs (axPlan / axPost): idempotent, overpayment-safe posting of
 * expense and salary payments into their permanent ledgers.
 */
class PaymentPostingService
{
    public function __construct(private readonly PeriodService $periods)
    {
    }

    /**
     * @param 'expense'|'salary' $kind
     * @param array{amount: float|string, paid_on: string, method?: ?string, account?: ?string, notes?: ?string, request_id?: ?string} $draft
     * @return array{action: 'saved'|'already_saved', payment: Model, remaining: float}
     */
    public function post(string $kind, int $sourceId, array $draft): array
    {
        $amount = (float) $draft['amount'];
        $requestId = $draft['request_id'] ?? (string) Str::uuid();
        $this->validateDraft($amount, $draft['paid_on']);
        $this->periods->assertOpen($draft['paid_on']);

        return DB::transaction(function () use ($kind, $sourceId, $draft, $amount, $requestId) {
            [$sourceModel, $ledger, $fk, $prefix] = $kind === 'expense'
                ? [OperatingExpense::class, ExpensePayment::class, 'operating_expense_id', 'EPAY-']
                : [Payroll::class, SalaryPayment::class, 'payroll_id', 'SPAY-'];

            /** @var OperatingExpense|Payroll $bill */
            $bill = $sourceModel::query()->lockForUpdate()->find($sourceId)
                ?? throw new PostingRejectedException('Expense / Payroll ID is missing or duplicated.');

            $repeat = $ledger::query()->where('request_id', $requestId)->get();
            if ($repeat->count() > 1) {
                throw new PostingRejectedException('Duplicate request IDs exist. Review saved history.');
            }
            if ($repeat->count() === 1) {
                $p = $repeat->first();
                $same = (int) $p->{$fk} === $bill->id && Money::paisa($p->amount) === Money::paisa($amount)
                    && $p->paid_on->toDateString() === CarbonImmutable::parse($draft['paid_on'])->toDateString()
                    && (string) $p->method === (string) ($draft['method'] ?? '') && (string) $p->account === (string) ($draft['account'] ?? '')
                    && (string) $p->notes === (string) ($draft['notes'] ?? '');
                if (! $same) {
                    throw new IdempotencyConflictException('This request was saved with different details. Review its saved history.');
                }

                return ['action' => 'already_saved', 'payment' => $p, 'remaining' => $this->remaining($kind, $bill)];
            }

            $this->assertBillValid($kind, $bill);
            $remaining = $this->remaining($kind, $bill);
            if ($amount > $remaining + 0.001) {
                throw new PostingRejectedException('Payment exceeds remaining balance BDT '.number_format($remaining, 2, '.', '').'.');
            }

            $payment = $ledger::query()->create([
                'code' => $prefix.$requestId,
                'paid_on' => $draft['paid_on'],
                $fk => $bill->id,
                'amount' => Money::round($amount),
                'method' => $draft['method'] ?? null,
                'account' => $draft['account'] ?? null,
                'notes' => $draft['notes'] ?? null,
                'origin' => $kind === 'expense' ? 'Recorded from expense' : 'Recorded from salary',
                'request_id' => $requestId,
                'posted_at' => now(),
                'posted_by' => Auth::user()?->email ?? 'system',
            ]);
            event(new PaymentPosted($kind, $payment->code, $amount));

            return ['action' => 'saved', 'payment' => $payment, 'remaining' => Money::round($remaining - $amount)];
        });
    }

    private function validateDraft(float $amount, string $date): void
    {
        if (! is_finite($amount) || $amount <= 0 || ! Money::hasAtMostTwoDecimals($amount)) {
            throw new PostingRejectedException('Enter a positive amount with at most two decimal places.');
        }
        $d = CarbonImmutable::parse($date, config('app.timezone'));
        if ($d->year < 2000 || $d->year > 2100) {
            throw new PostingRejectedException('Enter a valid actual payment date (2000-2100).');
        }
        if ($d->startOfDay()->isAfter(now()->startOfDay())) {
            throw new PostingRejectedException('Actual payment date cannot be in the future.');
        }
    }

    private function assertBillValid(string $kind, Model $bill): void
    {
        if ((float) $bill->{$kind === 'expense' ? 'amount' : 'gross_salary'} < 0) {
            throw new PostingRejectedException('Complete the source date, identity and incurred expense / gross salary first.');
        }
        if ($kind === 'expense' && $bill->subcategory === FinancialStatementService::PROCESSING_FEE) {
            throw new PostingRejectedException('Processing fee is withheld from a customer receipt; do not pay it a second time.');
        }
        if ($kind === 'salary') {
            $bad = EmployeeAdvance::query()->where('payroll_id', $bill->id)->where('type', 'Recovery')->where('amount', '<=', 0)->exists();
            if ($bad) {
                throw new PostingRejectedException('Linked advance recovery needs review.');
            }
        }
    }

    public function remaining(string $kind, Model $bill): float
    {
        if ($kind === 'expense') {
            return Money::round((float) $bill->amount - (float) ExpensePayment::query()->where('operating_expense_id', $bill->id)->sum('amount'));
        }
        $recovered = (float) EmployeeAdvance::query()->where('payroll_id', $bill->id)->where('type', 'Recovery')->sum('amount');
        $paid = (float) SalaryPayment::query()->where('payroll_id', $bill->id)->sum('amount');

        return Money::round((float) $bill->gross_salary - $recovered - $paid);
    }
}
