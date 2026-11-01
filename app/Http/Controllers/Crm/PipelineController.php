<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\MovePipelineRequest;
use App\Models\Lead;
use App\Services\Crm\CrmDashboardService;
use App\Services\Crm\CrmSettingsService;
use App\Services\Crm\LeadService;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function index(CrmDashboardService $dashboard, CrmSettingsService $settings): Response
    {
        return Inertia::render('Crm/Pipeline', [
            'columns' => $dashboard->pipeline(),
            'outcomes' => $settings->all()['outcomes'],
        ]);
    }

    /** Drag & drop moves a lead by choosing an outcome of the target stage. */
    public function move(MovePipelineRequest $request, Lead $lead, LeadService $leads)
    {
        $this->authorize('update', $lead);
        $leads->update($lead, ['outcome' => $request->validated('outcome')]);

        return $this->toast("Lead moved to {$lead->stage}.");
    }
}
