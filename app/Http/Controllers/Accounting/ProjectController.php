<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ClientSignatureRequest;
use App\Http\Requests\Accounting\DeleteWithReasonRequest;
use App\Http\Requests\Accounting\DeliverProjectRequest;
use App\Http\Requests\Accounting\StoreProjectRequest;
use App\Http\Requests\Accounting\UpdateProjectRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\RecordResource;
use App\Models\Project;
use App\Services\Accounting\ProjectService;
use App\Services\Accounting\ReferralService;
use App\Services\Shared\ListingService;
use App\Services\System\BrandingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function index(Request $request, ListingService $list): Response
    {
        return Inertia::render('Accounting/Projects/Index', [
            'projects' => ProjectResource::collection($list->page('projects', $request->query())),
            'filters' => $request->only(['q', 'kind', 'sort']),
        ]);
    }

    public function show(Project $project): Response
    {
        $project->load(['recognitions', 'referrals', 'payments']);

        return Inertia::render('Accounting/Projects/Show', [
            'project' => new ProjectResource($project),
            'recognitions' => RecordResource::collection($project->recognitions),
            'payments' => PaymentResource::collection($project->payments),
            'referrals' => $project->referrals->map(fn ($r) => (new RecordResource($r))->resolve() + ['status' => app(ReferralService::class)->status($r)])->values(),
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

    /** Printable Project Intake Form (client, project, domain / hosting, payment summary, signatures). */
    public function intake(Project $project, BrandingService $branding): Response
    {
        return Inertia::render('Accounting/Projects/Intake', ['project' => new ProjectResource($project)]);
    }

    public function signature(Project $project, string $format, BrandingService $branding): BinaryFileResponse
    {
        $file = $branding->signatureFile($project, $format) ?? abort(404);

        return response()->file($file['path'], ['Content-Type' => $file['mime'], 'Cache-Control' => 'private, max-age=31536000, immutable']);
    }

    public function storeSignature(ClientSignatureRequest $request, Project $project, BrandingService $branding)
    {
        $branding->setClientSignature($project, $request->file('client_signature'));

        return $this->toast("Client signature saved for {$project->code} — printed on this client's invoices and statements.");
    }

    public function destroySignature(Project $project, BrandingService $branding)
    {
        $branding->setClientSignature($project, null);

        return $this->toast("Client signature removed from {$project->code}.");
    }

    public function deliver(DeliverProjectRequest $request, Project $project)
    {
        $this->projects->update($project, $request->validated());

        return $this->toast("{$project->name} marked delivered; revenue is recognised by the daily automation.");
    }

    public function destroy(DeleteWithReasonRequest $request, Project $project)
    {
        $this->projects->delete($project, $request->validated('reason'));

        return redirect('/accounting/projects')->with('toast', ['type' => 'success', 'title' => 'Deleted', 'message' => "Project {$project->code} deleted."]);
    }
}
