<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\DeleteWithReasonRequest;
use App\Http\Requests\Accounting\StoreAdvanceRequest;
use App\Http\Requests\Accounting\UpdateAdvanceRequest;
use App\Http\Resources\RecordResource;
use App\Models\EmployeeAdvance;
use App\Services\Accounting\AdvanceService;
use App\Services\Accounting\EmployeeService;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvanceController extends Controller
{
    public function __construct(private readonly AdvanceService $advances) {}

    public function index(Request $request, ListingService $list, EmployeeService $employees): Response
    {
        return Inertia::render('Accounting/Advances/Index', [
            'advances' => RecordResource::collection($list->page('advances', $request->query())),
            'outstanding' => $this->advances->outstanding(),
            'employees' => $employees->options(),
        ]);
    }

    public function store(StoreAdvanceRequest $request)
    {
        $a = $this->advances->create($request->validated());

        return $this->toast("{$a->type} {$a->code} saved.");
    }

    public function update(UpdateAdvanceRequest $request, EmployeeAdvance $advance)
    {
        $this->advances->update($advance, $request->validated());

        return $this->toast("{$advance->type} {$advance->code} updated.");
    }

    public function destroy(DeleteWithReasonRequest $request, EmployeeAdvance $advance)
    {
        $this->advances->delete($advance, $request->validated('reason'));

        return $this->toast("{$advance->code} deleted.");
    }
}
