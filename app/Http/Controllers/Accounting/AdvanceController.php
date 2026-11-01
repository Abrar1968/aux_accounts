<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreAdvanceRequest;
use App\Http\Resources\RecordResource;
use App\Services\Accounting\AdvanceService;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvanceController extends Controller
{
    public function __construct(private readonly AdvanceService $advances) {}

    public function index(Request $request, ListingService $list): Response
    {
        return Inertia::render('Accounting/Advances/Index', [
            'advances' => RecordResource::collection($list->page('advances', $request->query())),
            'outstanding' => $this->advances->outstanding(),
        ]);
    }

    public function store(StoreAdvanceRequest $request)
    {
        $a = $this->advances->create($request->validated());

        return $this->toast("{$a->type} {$a->code} saved.");
    }
}
