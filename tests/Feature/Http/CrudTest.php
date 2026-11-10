<?php

namespace Tests\Feature\Http;

use App\Models\BankAccount;
use App\Models\CapitalTransaction;
use App\Models\Collaborator;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\ExpensePayment;
use App\Models\FixedAsset;
use App\Models\Liability;
use App\Models\MarketingService;
use App\Models\OperatingExpense;
use App\Models\PartnershipAssignment;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\RecurringExpense;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\PaymentPostingService;
use App\Support\Period;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** Every record type can be edited and deleted (or reversed), with guards where money depends on it. */
class CrudTest extends TestCase
{
    use SeedsWorkbook;

    private const WHY = ['reason' => 'Entered by mistake'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 10:00:00');
        $this->actingAs(User::query()->where('email', 'admin@auxtech.local')->firstOrFail());
    }

    private function ok($response): void
    {
        $response->assertSessionHasNoErrors()->assertSessionHas('toast.type', fn ($t) => in_array($t, ['success', 'warning'], true));
    }

    private function refused($response, string $text): void
    {
        $response->assertSessionHas('toast.type', 'error')->assertSessionHas('toast.message', fn ($m) => str_contains($m, $text));
    }

    public function test_project_update_and_guarded_delete(): void
    {
        $this->post('/accounting/projects', ['name' => 'Crud Shop', 'kind' => 'project', 'start_date' => '2026-11-01', 'revenue_method' => 'Point in Time', 'base_value' => 5000, 'referrer' => 'Rizwan']);
        $p = Project::query()->where('name', 'Crud Shop')->firstOrFail();
        $this->ok($this->put("/accounting/projects/{$p->id}", ['name' => 'Crud Shop 2', 'kind' => 'project', 'start_date' => '2026-11-01', 'revenue_method' => 'Point in Time', 'base_value' => 6000]));
        $this->assertSame('6000.00', $p->fresh()->base_value);

        $this->delete("/accounting/projects/{$p->id}", [])->assertSessionHasErrors('reason');
        $this->ok($this->delete("/accounting/projects/{$p->id}", self::WHY));
        $this->assertNull(Project::query()->find($p->id));
        $log = \App\Models\AuditLog::query()->where(['action' => 'deleted', 'auditable_type' => 'Project', 'auditable_id' => $p->id])->get();
        $this->assertSame(['Entered by mistake'], $log->pluck('reason')->all(), 'exactly one audit entry, carrying the reason');
        $this->assertSame(0, \App\Models\Referral::query()->where('project_id', $p->id)->count());

        $paid = Project::query()->whereHas('payments')->firstOrFail();
        $this->refused($this->delete("/accounting/projects/{$paid->id}", self::WHY), 'customer receipt');
    }

    public function test_customer_payment_edit_and_delete(): void
    {
        $proj = Project::query()->where('kind', 'project')->firstOrFail();
        $this->post('/accounting/payments', ['paid_at' => '2026-11-02 09:00', 'receivable_type' => 'project', 'receivable_id' => $proj->id, 'cash_received' => 1000, 'request_id' => (string) Str::uuid()]);
        $pay = CustomerPayment::query()->latest('id')->firstOrFail();
        $this->ok($this->put("/accounting/payments/{$pay->id}", ['paid_at' => '2026-11-01 10:00', 'receivable_type' => 'project', 'receivable_id' => $proj->id, 'cash_received' => 1200, 'processing_fee' => 25, 'method' => 'bKash']));
        $this->assertSame(['1200.00', '25.00', '2026-11-01'], [$pay->fresh()->cash_received, $pay->fresh()->processing_fee, substr((string) $pay->fresh()->paid_at, 0, 10)]);
        $this->ok($this->delete("/accounting/payments/{$pay->id}", self::WHY));
    }

    public function test_expense_delete_guard_and_payment_reversal(): void
    {
        $this->post('/accounting/expenses', ['expense_date' => '2026-11-02', 'category' => 'Office & Admin', 'description' => 'Crud bill', 'amount' => 900]);
        $bill = OperatingExpense::query()->where('description', 'Crud bill')->firstOrFail();
        app(PaymentPostingService::class)->post('expense', $bill->id, ['amount' => 400, 'paid_on' => '2026-11-02']);
        $this->refused($this->delete("/accounting/expenses/{$bill->id}", self::WHY), 'Reverse them');

        $pay = ExpensePayment::query()->where('operating_expense_id', $bill->id)->firstOrFail();
        $this->ok($this->post("/accounting/expense-payments/{$pay->id}/reverse", self::WHY));
        $this->assertSame(900.0, app(PaymentPostingService::class)->remaining('expense', $bill), 'balance restored');
        $this->refused($this->post("/accounting/expense-payments/{$pay->id}/reverse", self::WHY), 'already been reversed');

        // reversed bill still has (net zero) ledger rows → keep the audit trail; a fresh bill deletes cleanly
        $this->post('/accounting/expenses', ['expense_date' => '2026-11-02', 'category' => 'Office & Admin', 'description' => 'Crud bill 2', 'amount' => 100]);
        $b2 = OperatingExpense::query()->where('description', 'Crud bill 2')->firstOrFail();
        $this->delete("/accounting/expenses/{$b2->id}", self::WHY)->assertRedirect('/accounting/expenses');
        $this->assertNull(OperatingExpense::query()->find($b2->id));
    }

    public function test_payroll_update_delete_and_salary_reversal(): void
    {
        $emp = Employee::query()->create(['name' => 'Crud Person', 'slug' => 'crud person', 'base_salary' => 9000, 'is_active' => true]);
        $this->post('/accounting/payroll', ['employee_id' => $emp->id, 'period' => '2026-11-01', 'gross_salary' => 9000]);
        $pr = Payroll::query()->where('employee_id', $emp->id)->firstOrFail();
        $this->ok($this->put("/accounting/payroll/{$pr->id}", ['gross_salary' => 9500, 'notes' => 'raise']));
        app(PaymentPostingService::class)->post('salary', $pr->id, ['amount' => 9500, 'paid_on' => '2026-11-02']);
        $this->refused($this->delete("/accounting/payroll/{$pr->id}", self::WHY), 'Reverse those first');
        $sp = SalaryPayment::query()->where('payroll_id', $pr->id)->firstOrFail();
        $this->ok($this->post("/accounting/salary-payments/{$sp->id}/reverse", self::WHY));
        $this->assertSame(9500.0, app(PaymentPostingService::class)->remaining('salary', $pr->fresh()));
        $this->refused($this->delete("/accounting/employees/{$emp->id}"), 'Mark them inactive');
    }

    public function test_ledger_records_edit_and_delete(): void
    {
        $emp = Employee::query()->firstOrFail();
        $this->post('/accounting/advances', ['txn_date' => '2026-11-02', 'employee_id' => $emp->id, 'type' => 'Disbursement', 'amount' => 500]);
        $adv = EmployeeAdvance::query()->latest('id')->firstOrFail();
        $this->ok($this->put("/accounting/advances/{$adv->id}", ['txn_date' => '2026-11-02', 'employee_id' => $emp->id, 'type' => 'Disbursement', 'amount' => 700]));
        $this->assertSame('700.00', $adv->fresh()->amount);
        $this->ok($this->delete("/accounting/advances/{$adv->id}", self::WHY));

        $cap = CapitalTransaction::query()->firstOrFail();
        $this->post('/accounting/capital', ['txn_date' => '2026-11-02', 'investor_id' => $cap->investor_id, 'type' => 'Capital contribution', 'cash_impact' => 1000, 'equity_impact' => 1000]);
        $c = CapitalTransaction::query()->latest('id')->firstOrFail();
        $this->ok($this->put("/accounting/capital/{$c->id}", ['txn_date' => '2026-11-02', 'investor_id' => $cap->investor_id, 'type' => 'Capital contribution', 'cash_impact' => 2000, 'equity_impact' => 2000]));
        $this->ok($this->delete("/accounting/capital/{$c->id}", self::WHY));

        $this->post('/accounting/liabilities', ['txn_date' => '2026-11-02', 'liability_code' => 'LIA-DEF', 'description' => 'Crud', 'increase' => 300, 'cash_impact' => 0]);
        $l = Liability::query()->latest('id')->firstOrFail();
        $this->ok($this->put("/accounting/liabilities/{$l->id}", ['txn_date' => '2026-11-02', 'liability_code' => 'LIA-DEF', 'description' => 'Crud 2', 'increase' => 400, 'cash_impact' => 0]));
        $this->assertSame('Crud 2', $l->fresh()->description);
        $this->ok($this->delete("/accounting/liabilities/{$l->id}", self::WHY));

        $this->post('/accounting/assets', ['name' => 'Crud PC', 'acquisition_date' => '2026-11-01', 'in_service_date' => '2026-11-01', 'cost' => 1000, 'useful_life_months' => 12, 'cash_paid' => 1000, 'cash_payment_date' => '2026-11-01']);
        $a = FixedAsset::query()->where('name', 'Crud PC')->firstOrFail();
        $this->ok($this->delete("/accounting/assets/{$a->id}", self::WHY));

        $this->post('/accounting/marketing', ['service_month' => '2026-11-01', 'client' => 'Crud Client', 'revenue' => 500]);
        $m = MarketingService::query()->where('client', 'Crud Client')->firstOrFail();
        $this->ok($this->delete("/accounting/marketing/{$m->id}", self::WHY));
        $paidM = MarketingService::query()->whereIn('id', CustomerPayment::query()->where('receivable_type', 'marketing')->pluck('receivable_id'))->firstOrFail();
        $this->refused($this->delete("/accounting/marketing/{$paidM->id}", self::WHY), 'customer receipts');
    }

    public function test_banking_recurring_and_partnership_crud(): void
    {
        $this->post('/accounting/banking', ['name' => 'Crud Bank', 'type' => 'bank', 'is_active' => true]);
        $b = BankAccount::query()->where('name', 'Crud Bank')->firstOrFail();
        $this->delete("/accounting/banking/{$b->id}")->assertRedirect('/accounting/banking');
        $this->assertNull(BankAccount::query()->find($b->id));

        $this->post('/accounting/recurring', ['name' => 'Crud Rent', 'category' => 'Rent', 'amount' => 100, 'day_of_month' => 1, 'start_month' => '2026-11-01', 'is_active' => true]);
        $t = RecurringExpense::query()->where('name', 'Crud Rent')->firstOrFail();
        $this->ok($this->delete("/accounting/recurring/{$t->id}"));

        $this->post('/accounting/partnerships/collaborators', ['name' => 'Crud Partner', 'status' => 'Draft']);
        $col = Collaborator::query()->where('name', 'Crud Partner')->firstOrFail();
        $this->ok($this->put("/accounting/partnerships/collaborators/{$col->id}", ['name' => 'Crud Partner Ltd', 'status' => 'Draft']));
        $this->ok($this->delete("/accounting/partnerships/collaborators/{$col->id}"));
        $used = Collaborator::query()->whereIn('id', PartnershipAssignment::query()->pluck('collaborator_id'))->firstOrFail();
        $this->refused($this->delete("/accounting/partnerships/collaborators/{$used->id}"), 'assignments');
    }

    public function test_statements_still_balance_after_crud(): void
    {
        $this->test_expense_delete_guard_and_payment_reversal();
        $this->test_payroll_update_delete_and_salary_reversal();
        $svc = app(FinancialStatementService::class);
        $svc->flush();
        $this->assertEqualsWithDelta(0, $svc->forPeriod(Period::of('2026-11-01'))['balance_check'], 0.005);
    }
}
