<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmDashboardService;
use App\Services\Crm\DailyAllocationService;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function __invoke(CrmDashboardService $dashboard, DailyAllocationService $daily): Response
    {
        return Inertia::render('Crm/Today', [
            'summary' => $dashboard->summary(),
            'progress' => $daily->progress(),
            'due' => $dashboard->followUps()->filter(fn ($l) => in_array($l['priority'], ['OVERDUE', 'TODAY'], true))->take(50)->values(),
        ]);
    }
}
