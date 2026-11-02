<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    public function index(Request $request, CrmDashboardService $dashboard): Response
    {
        return Inertia::render('Crm/FollowUps', ['leads' => $dashboard->followUps($request->query('owner'))->values()]);
    }

    public function meetings(CrmDashboardService $dashboard): Response
    {
        return Inertia::render('Crm/Meetings', $dashboard->meetings());
    }
}
