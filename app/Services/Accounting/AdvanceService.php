<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\Payroll;
use App\Repositories\Contracts\EmployeeAdvanceRepositoryInterface;
use App\Services\Shared\AuditService;
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

        return $this->repo->create(array_merge($data, [
            'code' => 'ADV-'.str_pad((string) $this->sequences->next('ADV'), 3, '0', STR_PAD_LEFT),
            'payroll_id' => $this->payrollLink($data),
            'amount' => Money::round($data['amount']),
        ]));
    }

    public function update(EmployeeAdvance $a, array $data): EmployeeAdvance
    {
        $this->periods->assertOpen($a->txn_date, $data['txn_date'] ?? null);
        $merged = array_merge($a->only(['txn_date', 'employee_id', 'type', 'amount', 'related_salary_period']), $data);
        if ((float) $merged['amount'] <= 0) {
            throw new DomainException('Positive amount required');
        }

        return $this->repo->update($a, array_merge($data, ['payroll_id' => $this->payrollLink($merged), 'amount' => Money::round($merged['amount'])]));
    }

    public function delete(EmployeeAdvance $a, string $reason): void
    {
        $this->periods->assertOpen($a->txn_date);
        app(AuditService::class)->record('deleted', $a, $a->toArray(), null, $reason);
        $a->delete();
    }

    /** Recovery rows link to the payroll row of the related salary month (Employee_Advances!J). */
    private function payrollLink(array $data): ?int
    {
        if ($data['type'] !== 'Recovery') {
            return null;
        }
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $month = Period::of($data['related_salary_period'] ?? $data['txn_date']);

        return Payroll::query()->where('code', PayrollService::codeFor($month, $employee->name))->value('id')
            ?? throw new DomainException('Payroll link needs review: no payroll for '.$employee->name.' in '.$month->label().'.');
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
