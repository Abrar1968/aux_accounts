<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ConvertLeadRequest;
use App\Http\Requests\Crm\SetCallsMadeRequest;
use App\Http\Requests\Crm\StoreCallRequest;
use App\Http\Requests\Crm\StoreLeadRequest;
use App\Http\Requests\Crm\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Http\Resources\RecordResource;
use App\Models\Lead;
use App\Services\Crm\CallLogService;
use App\Services\Crm\CrmSettingsService;
use App\Services\Crm\LeadService;
use App\Services\Crm\PhoneNormalizer;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function index(Request $request, ListingService $list, CrmSettingsService $settings): Response
    {
        $filters = $request->query();
        if (! $request->user()->hasPermission('crm.leads.view_all') && ! $request->user()->hasPermission('crm.*')) {
            $filters['owner'] = $request->user()->name; // callers see their own leads
        }

        return Inertia::render('Crm/Leads/Index', [
            'leads' => LeadResource::collection($list->page('leads', $filters)),
            'options' => $settings->all(),
            'filters' => $request->only(['q', 'stage', 'owner', 'outcome', 'source']),
        ]);
    }

    public function show(Lead $lead, CrmSettingsService $settings): Response
    {
        $this->authorize('view', $lead);
        $lead->load('calls');
        $options = $settings->all();

        return Inertia::render('Crm/Leads/Show', [
            'outcomes' => $options['outcomes'],
            'options' => $options,
            'lead' => new LeadResource($lead),
            'calls' => RecordResource::collection($lead->calls->sortByDesc('recorded_at')->values()),
        ]);
    }

    public function store(StoreLeadRequest $request)
    {
        $lead = $this->leads->create($request->validated());

        return $this->toast("Lead {$lead->code} added.");
    }

    public function update(UpdateLeadRequest $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $this->leads->update($lead, $request->validated());

        return $this->toast("Lead {$lead->code} updated.");
    }

    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);
        $this->leads->archive($lead);

        return $this->toast("Lead {$lead->code} archived.");
    }

    public function storeCall(StoreCallRequest $request, Lead $lead, CallLogService $calls)
    {
        $this->authorize('update', $lead);
        $r = $calls->save($lead, $request->validated());

        return $this->toast($r['action'] === 'saved' ? 'Call saved; lead and counts updated.' : 'This call was already saved.');
    }

    public function callsMade(SetCallsMadeRequest $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $this->leads->setCallsMade($lead, $request->validated('calls_made'));

        return $this->toast('Call total updated; history retained.');
    }

    public function convert(ConvertLeadRequest $request, Lead $lead)
    {
        $project = $this->leads->convertToProject($lead, $request->validated());

        return redirect()->route('projects.show', $project)->with('toast', ['type' => 'success', 'title' => 'Converted', 'message' => "Project {$project->code} created from lead {$lead->code}."]);
    }

    /** JSON: live phone normalisation + duplicate check. */
    public function phoneCheck(Request $request, PhoneNormalizer $phones)
    {
        $n = $phones->normalize($request->query('phone'));

        return response()->json([
            'normalized' => $n,
            'valid' => $n !== null,
            'duplicates' => $n ? Lead::query()->where('normalized_phone', $n)->get(['id', 'code', 'outcome', 'owner']) : [],
        ]);
    }
}
