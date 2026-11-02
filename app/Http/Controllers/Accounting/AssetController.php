<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreAssetRequest;
use App\Http\Requests\Accounting\UpdateAssetRequest;
use App\Models\FixedAsset;
use App\Services\Accounting\AssetService;
use App\Support\Period;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function __construct(private readonly AssetService $assets) {}

    public function index(): Response
    {
        return Inertia::render('Accounting/Assets/Index', ['schedule' => $this->assets->schedule(Period::current())]);
    }

    public function store(StoreAssetRequest $request)
    {
        $a = $this->assets->create($request->validated());

        return $this->toast("Asset {$a->code} registered.");
    }

    public function update(UpdateAssetRequest $request, FixedAsset $asset)
    {
        $this->assets->update($asset, $request->validated());

        return $this->toast("Asset {$asset->code} updated.");
    }
}
