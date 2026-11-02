<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\SnapshotReportRequest;
use App\Http\Resources\RecordResource;
use App\Models\CrmReport;
use App\Services\Crm\AnalyticsService;
use App\Services\Crm\CrmReportService;
use App\Services\Shared\ListingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, ListingService $list): Response
    {
        return Inertia::render('Crm/Reports/Index', ['reports' => RecordResource::collection($list->page('crm_reports', $request->query() + ['sort' => '-period_start']))]);
    }

    public function show(CrmReport $report): Response
    {
        return Inertia::render('Crm/Reports/Show', ['report' => new RecordResource($report)]);
    }

    public function store(SnapshotReportRequest $request, CrmReportService $reports)
    {
        $r = $reports->snapshot($request->validated('type'), $request->validated('date'), 'Manual');

        return redirect()->route('crm.reports.show', $r)->with('toast', ['type' => 'success', 'title' => 'Report ready', 'message' => "{$r->type} report {$r->period_start->format('d M Y')} – {$r->period_end->format('d M Y')}."]);
    }

    public function analytics(Request $request, AnalyticsService $analytics): Response
    {
        $start = $request->query('from', '2026-10-07');
        $end = $request->query('to', CarbonImmutable::now(config('app.timezone'))->toDateString());

        return Inertia::render('Crm/Analytics', ['analytics' => $analytics->forPeriod($start, $end)]);
    }
}
