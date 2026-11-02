<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreRecognitionRequest;
use App\Http\Requests\Accounting\UpdateRecognitionRequest;
use App\Http\Resources\RecordResource;
use App\Models\RevenueRecognition;
use App\Services\Accounting\RevenueRecognitionService;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecognitionController extends Controller
{
    public function __construct(private readonly RevenueRecognitionService $recognition) {}

    public function index(Request $request, ListingService $list): Response
    {
        return Inertia::render('Accounting/Recognition/Index', [
            'rows' => RecordResource::collection($list->page('recognitions', $request->query())),
            'filters' => $request->only(['q', 'from', 'to']),
        ]);
    }

    public function store(StoreRecognitionRequest $request)
    {
        $row = $this->recognition->create($request->validated());

        return $this->toast("Recognition {$row->code}: {$row->entry_check}.", $row->entry_check === 'OK' ? 'success' : 'warning');
    }

    public function update(UpdateRecognitionRequest $request, RevenueRecognition $recognition)
    {
        $row = $this->recognition->update($recognition, $request->validated());

        return $this->toast("Recognition {$row->code}: {$row->entry_check}.", $row->entry_check === 'OK' ? 'success' : 'warning');
    }

    public function destroy(RevenueRecognition $recognition)
    {
        $this->recognition->delete($recognition);

        return $this->toast('Recognition row removed.');
    }
}
