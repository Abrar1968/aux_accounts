<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\FinancialReportService;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Profit & Loss, Cash Flow Statement and Trial Balance for any month range. */
class ReportController extends Controller
{
    public function __construct(private readonly FinancialReportService $reports) {}

    public function profitLoss(Request $request): Response
    {
        [$from, $to] = $this->reports->range($request->query('from'), $request->query('to'));
        $compare = in_array($request->query('compare'), ['previous', 'year', 'none'], true) ? $request->query('compare') : 'previous';

        return Inertia::render('Accounting/Reports/ProfitLoss', [
            'report' => $this->reports->profitAndLoss($from, $to, $compare), 'compareMode' => $compare,
            'presets' => $this->reports->presets(), 'view' => $request->query('view') === 'monthly' ? 'monthly' : 'summary',
        ]);
    }

    public function cashFlow(Request $request): Response
    {
        [$from, $to] = $this->reports->range($request->query('from'), $request->query('to'));

        return Inertia::render('Accounting/Reports/CashFlow', [
            'report' => $this->reports->cashFlow($from, $to), 'presets' => $this->reports->presets(),
        ]);
    }

    public function trialBalance(Request $request): Response
    {
        [, $asAt] = $this->reports->range(null, $request->query('month'));
        $from = $request->query('from') === 'start' ? null : ($request->query('from') ? Period::of((string) $request->query('from')) : null);
        $report = $request->query('from') === 'start'
            ? $this->reports->trialBalance($asAt, $this->reports->range('2000-01', $asAt->key())[0])
            : $this->reports->trialBalance($asAt, $from);

        return Inertia::render('Accounting/Reports/TrialBalance', [
            'report' => $report, 'fiscalLabel' => $this->reports->fiscalLabel($asAt),
            'mode' => $request->query('from') === 'start' ? 'start' : 'fy',
        ]);
    }
}
