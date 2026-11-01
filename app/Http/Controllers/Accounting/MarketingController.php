<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreMarketingRequest;
use App\Http\Requests\Accounting\UpdateMarketingRequest;
use App\Models\MarketingService;
use App\Services\Accounting\MarketingRevenueService;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    public function __construct(private readonly MarketingRevenueService $marketing)
    {
    }

    public function index(Request $request, ListingService $list): Response
    {
        $page = $list->page('marketing', $request->query());
        $page->through(fn ($m) => (new \App\Http\Resources\RecordResource($m))->resolve() + $this->marketing->status($m));

        return Inertia::render('Accounting/Marketing/Index', ['services' => $page, 'filters' => $request->only(['q'])]);
    }

    public function store(StoreMarketingRequest $request)
    {
        $m = $this->marketing->create($request->validated());

        return $this->toast("Service {$m->code} created.");
    }

    public function update(UpdateMarketingRequest $request, MarketingService $marketing)
    {
        $this->marketing->update($marketing, $request->validated());

        return $this->toast("Service {$marketing->code} updated.");
    }
}
