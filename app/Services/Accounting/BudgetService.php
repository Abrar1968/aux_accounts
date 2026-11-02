<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use App\Support\Money;
use App\Support\Period;

/**
 * Budgets vs actual per statement line (FR-ACC-84, new). Actuals come from FinancialStatementService.
 */
class BudgetService
{
    /** Lines that can carry a budget (income-statement lines). Revenue lines: higher is better. */
    public const LINES = [
        'project_revenue', 'marketing_revenue', 'partnership_revenue', 'total_revenue',
        'salary_expense', 'referral_paid', 'rent', 'marketing_advertising', 'software_hosting', 'office_admin',
        'legal_license', 'bank_fees', 'repairs_equipment', 'bonuses', 'other_operating', 'depreciation', 'total_expenses', 'net_income',
    ];

    private const INCOME = ['project_revenue', 'marketing_revenue', 'partnership_revenue', 'total_revenue', 'net_income'];

    public function __construct(
        private readonly BudgetRepositoryInterface $budgets,
        private readonly FinancialStatementService $statements,
    ) {}

    /** @param array<string, float|string|null> $amounts line_key => amount (null/'' removes) */
    public function save(Period $p, array $amounts): int
    {
        $n = 0;
        foreach ($amounts as $key => $amount) {
            if (! in_array($key, self::LINES, true)) {
                throw new DomainException("{$key} cannot be budgeted.");
            }
            if ($amount === null || $amount === '') {
                $this->budgets->query()->where(['period' => $p->date(), 'line_key' => $key])->delete();

                continue;
            }
            $this->budgets->query()->updateOrCreate(['period' => $p->date(), 'line_key' => $key], ['amount' => Money::round($amount)]);
            $n++;
        }

        return $n;
    }

    /** Copies the previous month's budgets into an empty month. */
    public function copyPrevious(Period $p): int
    {
        if ($this->budgets->query()->where('period', $p->date())->exists()) {
            throw new DomainException($p->label().' already has budgets.');
        }
        $prev = $this->budgets->query()->where('period', $p->previous()->date())->get();
        foreach ($prev as $b) {
            $this->budgets->create(['period' => $p->date(), 'line_key' => $b->line_key, 'amount' => $b->amount]);
        }

        return $prev->count();
    }

    public function compare(Period $p): array
    {
        $actual = $this->statements->forPeriod($p);
        $budget = $this->budgets->query()->where('period', $p->date())->pluck('amount', 'line_key');
        $rows = [];
        foreach (self::LINES as $key) {
            $b = isset($budget[$key]) ? (float) $budget[$key] : null;
            $a = (float) $actual[$key];
            $variance = $b === null ? null : Money::round($a - $b);
            $favourable = $variance === null ? null : (in_array($key, self::INCOME, true) ? $variance >= 0 : $variance <= 0);
            $rows[] = [
                'key' => $key, 'label' => FinancialStatementService::LINES[$key][0], 'income' => in_array($key, self::INCOME, true),
                'budget' => $b, 'actual' => $a, 'variance' => $variance,
                'variance_pct' => ($b !== null && $b != 0.0) ? round(($a - $b) / abs($b), 4) : null,
                'favourable' => $favourable, 'total' => str_starts_with($key, 'total_') || $key === 'net_income',
            ];
        }

        return $rows;
    }
}
