<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Referral;
use App\Repositories\Contracts\PayrollRepositoryInterface;
use App\Services\Shared\AuditService;
use App\Services\Shared\PeriodService;
use App\Support\Money;
use App\Support\Period;
use Illuminate\Support\Collection;

/**
 * Salary tab: one payroll per employee/month, id PAYROLL-YYYYMM-<lower(trim(name))>.
 */
class PayrollService
{
    public function __construct(
        private readonly PayrollRepositoryInterface $repo,
        private readonly PeriodService $periods,
    ) {}

    public static function codeFor(Period $p, string $employeeName): string
    {
        return 'PAYROLL-'.$p->start->format('Ym').'-'.mb_strtolower(trim($employeeName));
    }

    public function create(int $employeeId, string $period, float $gross, ?string $notes = null): Payroll
    {
        $p = Period::of($period);
        $this->periods->assertOpen($p->date());
        $employee = Employee::query()->findOrFail($employeeId);
        if ($this->repo->query()->where('employee_id', $employeeId)->where('period', $p->date())->exists()) {
            throw new DomainException('Duplicate employee/month.');
        }
        if ($gross < 0) {
            throw new DomainException('Invalid salary');
        }

        return $this->repo->create([
            'code' => self::codeFor($p, $employee->name),
            'period' => $p->date(),
            'employee_id' => $employeeId,
            'gross_salary' => Money::round($gross),
            'notes' => $notes,
        ]);
    }

    /** Draft a payroll row for every active employee with a base salary (new feature). */
    public function draftMonth(Period $p): Collection
    {
        $created = collect();
        foreach (Employee::query()->where('is_active', true)->whereNotNull('base_salary')->get() as $e) {
            if (! $this->repo->query()->where('employee_id', $e->id)->where('period', $p->date())->exists()) {
                $created->push($this->create($e->id, $p->date(), (float) $e->base_salary, 'Drafted from base salary'));
            }
        }

        return $created;
    }

    /** Salary!D:T derived columns. */
    public function summary(Payroll $pr): array
    {
        $recovery = Money::sum($pr->recoveries()->pluck('amount'));
        $cash = Money::sum($pr->payments()->pluck('amount'));
        $p = Period::of($pr->period);
        $referral = Money::round(Referral::query()->where('referrer', $pr->employee->name)
            ->where('paid_on', '>=', $p->date())->where('paid_on', '<', $p->cut())->sum('paid_amount'));
        $gross = (float) $pr->gross_salary;
        $unpaid = max(0.0, Money::round($gross - $recovery - $cash));
        $check = $recovery + $cash > $gross + 0.01 ? 'Overpayment/recovery - review' : 'OK';

        return [
            'referral_paid' => $referral,
            'advance_recovery' => $recovery,
            'unpaid' => $unpaid,
            'cash_paid' => Money::round($cash + $referral),
            'salary_cash_settled' => $cash,
            'entry_check' => $check,
            'status' => $check !== 'OK' ? $check : ($unpaid <= 0.01 ? 'Settled' : ($recovery + $cash > 0 ? 'Partly settled' : 'Unpaid')),
        ];
    }

    public function update(Payroll $pr, array $data): Payroll
    {
        $this->periods->assertOpen($pr->period);
        if (isset($data['gross_salary']) && (float) $data['gross_salary'] < 0) {
            throw new DomainException('Invalid salary');
        }

        return $this->repo->update($pr, array_intersect_key($data, array_flip(['gross_salary', 'notes'])));
    }

    /** Delete a payroll row drafted by mistake (no salary paid, no advance recovered against it). */
    public function delete(Payroll $pr, string $reason): void
    {
        $this->periods->assertOpen($pr->period);
        if ($pr->payments()->exists() || $pr->recoveries()->exists()) {
            throw new DomainException("{$pr->code} has salary payments or advance recoveries. Reverse those first.");
        }
        app(AuditService::class)->record('deleted', $pr, $pr->toArray(), null, $reason);
        $pr->delete();
    }
}
