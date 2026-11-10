<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\DeleteWithReasonRequest;
use App\Http\Requests\Accounting\PostPaymentRequest;
use App\Http\Requests\Accounting\StorePayrollRequest;
use App\Http\Requests\Accounting\UpdatePayrollRequest;
use App\Http\Resources\PayrollResource;
use App\Http\Resources\RecordResource;
use App\Models\Payroll;
use App\Models\SalaryPayment;
use App\Services\Accounting\PaymentPostingService;
use App\Services\Accounting\PayrollService;
use App\Services\Shared\ListingService;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    public function index(Request $request, ListingService $list): Response
    {
        $month = $request->query('month', Period::current()->key());

        return Inertia::render('Accounting/Payroll/Index', [
            'month' => $month,
            'payrolls' => PayrollResource::collection($list->page('payrolls', ['month' => $month, 'per_page' => 100] + $request->query())),
        ]);
    }

    public function store(StorePayrollRequest $request)
    {
        $d = $request->validated();
        $p = $this->payroll->create((int) $d['employee_id'], $d['period'], (float) $d['gross_salary'], $d['notes'] ?? null);

        return $this->toast("Payroll {$p->code} created.");
    }

    public function draft(string $month)
    {
        $n = $this->payroll->draftMonth(Period::of($month))->count();

        return $this->toast("{$n} payroll rows drafted from base salaries.");
    }

    public function pay(PostPaymentRequest $request, Payroll $payroll, PaymentPostingService $posting)
    {
        $r = $posting->post('salary', $payroll->id, $request->validated());

        return $this->toast(($r['action'] === 'already_saved' ? 'Already saved ' : 'SAVED ').$r['payment']->code.'. Remaining ৳'.number_format($r['remaining'], 2).'.');
    }

    public function payslip(Payroll $payroll): Response
    {
        $payroll->load('employee', 'payments', 'recoveries');

        return Inertia::render('Accounting/Payroll/Payslip', [
            'payroll' => new PayrollResource($payroll),
            'payments' => RecordResource::collection($payroll->payments),
            'recoveries' => RecordResource::collection($payroll->recoveries),
        ]);
    }

    public function update(UpdatePayrollRequest $request, Payroll $payroll)
    {
        $this->payroll->update($payroll, $request->validated());

        return $this->toast("{$payroll->code} updated.");
    }

    public function destroy(DeleteWithReasonRequest $request, Payroll $payroll)
    {
        $this->payroll->delete($payroll, $request->validated('reason'));

        return $this->toast("{$payroll->code} deleted.");
    }

    public function reversePayment(DeleteWithReasonRequest $request, SalaryPayment $salaryPayment, PaymentPostingService $posting)
    {
        $rev = $posting->reverse('salary', $salaryPayment, $request->validated('reason'));

        return $this->toast("Salary payment reversed ({$rev->code}).", 'success', 'Reversed');
    }
}
