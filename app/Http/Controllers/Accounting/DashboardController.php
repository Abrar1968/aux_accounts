<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\DashboardService;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $month = $request->query('month') ? Period::of($request->query('month')) : Period::current();

        return Inertia::render('Dashboard', ['overview' => $dashboard->overview($month), 'month' => $month->key()]);
    }
}
