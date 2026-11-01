<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreLiabilityRequest;
use App\Services\Accounting\LiabilityService;
use Inertia\Inertia;
use Inertia\Response;

class LiabilityController extends Controller
{
    public function __construct(private readonly LiabilityService $liabilities) {}

    public function index(): Response
    {
        return Inertia::render('Accounting/Liabilities/Index', [
            'ledger' => $this->liabilities->ledger(), 'siam' => $this->liabilities->siamTracker(), 'types' => LiabilityService::TYPES,
        ]);
    }

    public function store(StoreLiabilityRequest $request)
    {
        $this->liabilities->create($request->validated());

        return $this->toast('Liability entry saved.');
    }
}
