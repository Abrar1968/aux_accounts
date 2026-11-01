<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\System\AutomationMonitorService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function __construct(private readonly AutomationMonitorService $monitor) {}

    public function index(): Response
    {
        return Inertia::render('System/Automation', $this->monitor->overview());
    }

    public function audit(Request $request): Response
    {
        return Inertia::render('System/Audit', ['logs' => $this->monitor->audit($request->query()), 'filters' => $request->only(['type', 'user_id'])]);
    }
}
