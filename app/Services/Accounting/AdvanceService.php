<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\Payroll;
use App\Repositories\Contracts\EmployeeAdvanceRepositoryInterface;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SequenceService;
use App\Support\Money;
use App\Support\Period;

/**
 * Employee_Advances: Disbursement (cash out, asset) / Recovery (non-cash salary deduction).
 */
class AdvanceService
{
    public function __construct(
        private readonly EmployeeAdvanceRepositoryInterface $repo,
        private readonly SequenceService $sequences,
        private readonly PeriodService $periods,
    ) {}

    public function create(array $data): EmployeeAdvance
    {
        $this->periods->assertOpen($data['txn_date']);
        if (! in_array($data['type'], ['Disbursement', 'Recovery'], true)) {
            throw new DomainException('Type must be Disbursement or Recovery');
        }
        if ((float) $data['amount'] <= 0) {
            throw new DomainException('Positive amount required');
        }
        $payrollId = null;
        if ($data['type'] === 'Recovery') {
            $employee = Employee::query()->findOrFail($data['employee_id']);
            $month = Period::of($data['related_salary_period'] ?? $data['txn_date']);
            $payroll = Payroll::query()->where('code', PayrollService::codeFor($month, $employee->name))->first()
                ?? throw new DomainException('Payroll link needs review: no payroll for '.$employee->name.' in '.$month->label().'.');
            $payrollId = $payroll->id;
        }

        return $this->repo->create($data + [
            'code' => 'ADV-'.str_pad((string) $this->sequences->next('ADV'), 3, '0', STR_PAD_LEFT),
            'payroll_id' => $payrollId,
            'amount' => Money::round($data['amount']),
        ]);
    }

    /** Outstanding advance per employee at a cut-off date. */
    public function outstanding(?string $cut = null): array
    {
        $q = $this->repo->query()->with('employee');
        if ($cut) {
            $q->where('txn_date', '<', $cut);
        }
        $out = [];
        foreach ($q->get() as $a) {
            $name = $a->employee->name;
            $out[$name] = Money::round(($out[$name] ?? 0) + ($a->type === 'Disbursement' ? 1 : -1) * (float) $a->amount);
        }

        return $out;
    }
}
