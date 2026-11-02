<?php

namespace Tests\Feature\Notifications;

use App\Exceptions\Domain\PostingRejectedException;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\OperatingExpense;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\User;
use App\Notifications\CrmReportReady;
use App\Notifications\DealWon;
use App\Notifications\PaymentRecorded;
use App\Notifications\PostingRejected;
use App\Services\Accounting\CustomerPaymentService;
use App\Services\Accounting\PaymentPostingService;
use App\Services\Crm\CrmReportService;
use App\Services\Crm\LeadService;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** Clicking a notification must land on the exact record (docs/07 §4). */
class NotificationDeepLinksTest extends TestCase
{
    use SeedsWorkbook;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 10:00:00');
        $this->owner = User::query()->where('email', 'admin@auxtech.local')->firstOrFail();
        $this->actingAs($this->owner);
        Notification::fake();
    }

    /** The URL must open (200) and contain the record identity. */
    private function assertOpens(string $url, string $mustContain): void
    {
        $this->assertStringContainsString($mustContain, $url);
        $this->get($url)->assertOk();
    }

    private function urlOf(string $class): string
    {
        $url = null;
        Notification::assertSentTo($this->owner, $class, function ($n) use (&$url) {
            $url = $n->url;

            return true;
        });

        return (string) $url;
    }

    public function test_customer_receipt_links_to_its_row(): void
    {
        $p = app(CustomerPaymentService::class)->record(['receivable_type' => 'project', 'receivable_id' => Project::query()->value('id'),
            'paid_at' => '2026-11-02 09:00:00', 'cash_received' => 1000, 'processing_fee' => 0]);
        $this->assertOpens($this->urlOf(PaymentRecorded::class), 'highlight='.$p->code);
    }

    public function test_expense_payment_links_to_the_bill(): void
    {
        $bill = OperatingExpense::query()->create(['code' => 'EXP-DL1', 'accrual_month' => '2026-11-01', 'category' => 'Rent', 'description' => 'x', 'amount' => 10]);
        app(PaymentPostingService::class)->post('expense', $bill->id, ['amount' => 10, 'paid_on' => '2026-11-02']);
        $this->assertOpens($this->urlOf(PaymentRecorded::class), '/accounting/expenses/'.$bill->id.'?highlight=EPAY-');
    }

    public function test_salary_payment_links_to_the_payroll_row(): void
    {
        $emp = Employee::query()->create(['name' => 'Link Tester', 'slug' => 'link tester', 'base_salary' => 5000, 'is_active' => true]);
        $row = Payroll::query()->create(['employee_id' => $emp->id, 'period' => '2026-10-01', 'code' => 'PAYROLL-202610-link tester', 'gross_salary' => 5000]);
        app(PaymentPostingService::class)->post('salary', $row->id, ['amount' => 100, 'paid_on' => '2026-11-02']);
        $this->assertOpens($this->urlOf(PaymentRecorded::class), '/accounting/payroll?month=2026-10&highlight='.rawurlencode($row->code));
    }

    public function test_rejected_posting_notifies_actor_with_bill_link(): void
    {
        $bill = OperatingExpense::query()->create(['code' => 'EXP-DL2', 'accrual_month' => '2026-11-01', 'category' => 'Rent', 'description' => 'x', 'amount' => 10]);
        try {
            app(PaymentPostingService::class)->post('expense', $bill->id, ['amount' => 50, 'paid_on' => '2026-11-02']);
            $this->fail('overpayment accepted');
        } catch (PostingRejectedException) {
        }
        $this->assertOpens($this->urlOf(PostingRejected::class), '/accounting/expenses/'.$bill->id);
    }

    public function test_lead_and_report_notifications_open_the_record(): void
    {
        $lead = Lead::query()->where('stage', 'Interested')->firstOrFail();
        app(LeadService::class)->update($lead, ['outcome' => 'Won', 'deal_value' => 30000]);
        $this->assertOpens($this->urlOf(DealWon::class), '/crm/leads/'.$lead->id);

        $report = app(CrmReportService::class)->snapshot('Monthly', '2026-10-31', 'Automatic');
        $this->assertOpens($this->urlOf(CrmReportReady::class), '/crm/reports/'.$report->id);
    }
}
