<?php

namespace Tests\Feature\Services;

use App\Exceptions\Domain\IdempotencyConflictException;
use App\Exceptions\Domain\PeriodLockedException;
use App\Exceptions\Domain\PostingRejectedException;
use App\Models\ExpensePayment;
use App\Models\OperatingExpense;
use App\Models\Payroll;
use App\Services\Accounting\PaymentPostingService;
use App\Services\Shared\PeriodService;
use App\Support\Period;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** AuxtechPayments.gs behaviours (testAuxtechPaymentsOnCopy equivalents). */
class PaymentPostingServiceTest extends TestCase
{
    use SeedsWorkbook;

    private PaymentPostingService $posting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 10:00:00');
        $this->posting = app(PaymentPostingService::class);
    }

    private function bill(float $amount = 100): OperatingExpense
    {
        return OperatingExpense::query()->create(['code' => 'EXP-T'.Str::random(4), 'accrual_month' => '2026-11-01', 'expense_date' => '2026-11-01',
            'category' => 'Office & Admin', 'description' => 'Synthetic bill', 'amount' => $amount]);
    }

    public function test_installments_idempotency_and_overpayment(): void
    {
        $bill = $this->bill(100);
        $req = (string) Str::uuid();
        $first = $this->posting->post('expense', $bill->id, ['amount' => 30, 'paid_on' => '2026-11-02', 'request_id' => $req]);
        $this->assertSame('saved', $first['action']);
        $this->assertSame(70.0, $first['remaining']);

        $retry = $this->posting->post('expense', $bill->id, ['amount' => 30, 'paid_on' => '2026-11-02', 'request_id' => $req]);
        $this->assertSame('already_saved', $retry['action']);
        $this->assertSame(1, ExpensePayment::query()->where('operating_expense_id', $bill->id)->count());

        try {
            $this->posting->post('expense', $bill->id, ['amount' => 71, 'paid_on' => '2026-11-02', 'request_id' => (string) Str::uuid()]);
            $this->fail('overpayment accepted');
        } catch (PostingRejectedException $e) {
            $this->assertStringContainsString('remaining balance BDT 70.00', $e->getMessage());
        }
        $last = $this->posting->post('expense', $bill->id, ['amount' => 70, 'paid_on' => '2026-11-02', 'request_id' => (string) Str::uuid()]);
        $this->assertSame(0.0, $last['remaining']);
    }

    public function test_same_request_with_different_details_conflicts(): void
    {
        $bill = $this->bill();
        $req = (string) Str::uuid();
        $this->posting->post('expense', $bill->id, ['amount' => 10, 'paid_on' => '2026-11-02', 'request_id' => $req]);
        $this->expectException(IdempotencyConflictException::class);
        $this->posting->post('expense', $bill->id, ['amount' => 11, 'paid_on' => '2026-11-02', 'request_id' => $req]);
    }

    public function test_draft_validation(): void
    {
        $bill = $this->bill();
        foreach ([['amount' => -1], ['amount' => 10.555], ['amount' => 10, 'paid_on' => '2026-11-03'], ['amount' => 10, 'paid_on' => '1999-12-31']] as $bad) {
            try {
                $this->posting->post('expense', $bill->id, $bad + ['paid_on' => '2026-11-02']);
                $this->fail('accepted '.json_encode($bad));
            } catch (PostingRejectedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_withheld_processing_fee_cannot_be_paid_again(): void
    {
        $fee = OperatingExpense::query()->where('subcategory', 'Processing fee')->first();
        $this->expectException(PostingRejectedException::class);
        $this->posting->post('expense', $fee->id, ['amount' => 1, 'paid_on' => '2026-11-02']);
    }

    public function test_salary_remaining_subtracts_advance_recoveries(): void
    {
        // Abrar Oct-2026: gross 40,000 − recovery 23,633 − paid 16,170 = 197 remaining (workbook F66).
        $payroll = Payroll::query()->where('code', 'PAYROLL-202610-abrar')->first();
        $this->assertSame(197.0, $this->posting->remaining('salary', $payroll));
        $this->expectException(PostingRejectedException::class);
        $this->posting->post('salary', $payroll->id, ['amount' => 198, 'paid_on' => '2026-11-02']);
    }

    public function test_closed_period_blocks_posting(): void
    {
        app(PeriodService::class)->close(Period::of('2026-11'), null);
        $this->expectException(PeriodLockedException::class);
        $this->posting->post('expense', $this->bill()->id, ['amount' => 1, 'paid_on' => '2026-11-02']);
    }
}
