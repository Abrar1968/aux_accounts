<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Shared\PeriodService;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatementController extends Controller
{
    public function __construct(private readonly FinancialStatementService $statements)
    {
    }

    public function show(?string $month = null): Response
    {
        $p = $month ? Period::of($month) : Period::current();

        return Inertia::render('Accounting/Statements/Show', [
            'month' => $p->key(), 'label' => $p->label(), 'lines' => $this->statements->lines($p),
        ]);
    }

    public function grid(Request $request, PeriodService $periods): Response
    {
        $to = $request->query('to') ? Period::of($request->query('to')) : Period::current();
        $from = $request->query('from') ? Period::of($request->query('from')) : $periods->modelStart();

        return Inertia::render('Accounting/Statements/Grid', [
            'lines' => FinancialStatementService::LINES, 'grid' => $this->statements->grid($from, $to),
        ]);
    }
}
