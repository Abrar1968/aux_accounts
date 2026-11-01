<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ClosePeriodRequest;
use App\Http\Requests\Accounting\ReopenPeriodRequest;
use App\Services\Shared\PeriodService;
use App\Support\Period;
use Inertia\Inertia;
use Inertia\Response;

class PeriodController extends Controller
{
    public function __construct(private readonly PeriodService $periods)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Accounting/Periods', ['periods' => $this->periods->list()]);
    }

    public function close(ClosePeriodRequest $request, string $month)
    {
        $p = $this->periods->close(Period::of($month), $request->user()->id, $request->validated('notes'));

        return $this->toast(Period::of($p->month)->label().' closed. Entries in this month are now locked.');
    }

    public function reopen(ReopenPeriodRequest $request, string $month)
    {
        $this->periods->reopen(Period::of($month), $request->validated('reason'));

        return $this->toast(Period::of($month)->label().' reopened.', 'warning', 'Period reopened');
    }
}
