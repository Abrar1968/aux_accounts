<?php

namespace App\Services\Accounting;

use App\Services\Shared\PeriodService;
use App\Support\Period;

/**
 * Dashboard tab: KPI trend per month + headline numbers for the selected month.
 */
class DashboardService
{
    public function __construct(
        private readonly FinancialStatementService $statements,
        private readonly PeriodService $periods,
        private readonly ReceivableService $receivables,
    ) {}

    public function overview(Period $through): array
    {
        $trend = [];
        foreach (Period::range($this->periods->modelStart(), $through) as $p) {
            $s = $this->statements->forPeriod($p);
            $trend[] = [
                'period' => $p->date(),
                'label' => $p->label(),
                'revenue' => $s['total_revenue'],
                'expenses' => $s['total_expenses'],
                'net_income' => $s['net_income'],
                'ending_cash' => $s['ending_cash'],
                'accounts_receivable' => $s['accounts_receivable'],
                'advance_receivable' => $s['advance_receivable'],
                'total_assets' => $s['total_assets'],
                'total_liabilities' => $s['total_liabilities'],
                'total_equity' => $s['total_equity'],
                'balance_check' => $s['balance_check'],
            ];
        }
        $current = $this->statements->forPeriod($through);
        $topAr = $this->receivables->balancesAt($through->cut())->sortByDesc('ar')->take(5)->values();

        return [
            'period' => $through->date(),
            'kpis' => $current,
            'trend' => $trend,
            'top_receivables' => $topAr,
            'expense_mix' => collect(FinancialStatementService::NAMED_CATEGORIES)
                ->map(fn ($label, $key) => ['label' => $label, 'amount' => $current[$key]])
                ->push(['label' => 'Salary', 'amount' => $current['salary_expense']])
                ->push(['label' => 'Other', 'amount' => $current['other_operating']])
                ->filter(fn ($r) => $r['amount'] > 0)->sortByDesc('amount')->values(),
        ];
    }
}
