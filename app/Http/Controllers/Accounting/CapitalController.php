<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreCapitalRequest;
use App\Http\Resources\RecordResource;
use App\Services\Accounting\CapitalService;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CapitalController extends Controller
{
    public function __construct(private readonly CapitalService $capital) {}

    public function index(Request $request, ListingService $list): Response
    {
        return Inertia::render('Accounting/Capital/Index', [
            'cap_table' => $this->capital->capTable($request->query('as_of')),
            'transactions' => RecordResource::collection($list->page('capital', $request->query())),
        ]);
    }

    public function store(StoreCapitalRequest $request)
    {
        $this->capital->create($request->validated());

        return $this->toast('Capital transaction saved.');
    }
}
