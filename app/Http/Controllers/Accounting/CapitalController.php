<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\DeleteWithReasonRequest;
use App\Http\Requests\Accounting\StoreCapitalRequest;
use App\Http\Requests\Accounting\UpdateCapitalRequest;
use App\Http\Resources\RecordResource;
use App\Models\CapitalTransaction;
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

    public function update(UpdateCapitalRequest $request, CapitalTransaction $capital)
    {
        $this->capital->update($capital, $request->validated());

        return $this->toast('Capital entry updated.');
    }

    public function destroy(DeleteWithReasonRequest $request, CapitalTransaction $capital)
    {
        $this->capital->delete($capital, $request->validated('reason'));

        return $this->toast('Capital entry deleted.');
    }
}
