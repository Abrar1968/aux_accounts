<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\DeliverProjectRequest;
use App\Http\Requests\Accounting\StoreProjectRequest;
use App\Http\Requests\Accounting\UpdateProjectRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\RecordResource;
use App\Models\Project;
use App\Services\Accounting\ProjectService;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects)
    {
    }

    public function index(Request $request, ListingService $list): Response
    {
        return Inertia::render('Accounting/Projects/Index', [
            'projects' => ProjectResource::collection($list->page('projects', $request->query())),
            'filters' => $request->only(['q', 'kind', 'sort']),
        ]);
    }

    public function show(Project $project): Response
    {
        $project->load(['recognitions', 'referral', 'payments']);

        return Inertia::render('Accounting/Projects/Show', [
            'project' => new ProjectResource($project),
            'recognitions' => RecordResource::collection($project->recognitions),
            'payments' => PaymentResource::collection($project->payments),
            'referral' => $project->referral ? new RecordResource($project->referral) : null,
        ]);
    }

    public function store(StoreProjectRequest $request)
    {
        $p = $this->projects->create($request->validated());

        return $this->toast("Project {$p->code} created.");
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $this->projects->update($project, $request->validated());

        return $this->toast("Project {$project->code} updated.");
    }

    public function deliver(DeliverProjectRequest $request, Project $project)
    {
        $this->projects->update($project, $request->validated());

        return $this->toast("{$project->name} marked delivered; revenue is recognised by the daily automation.");
    }
}
