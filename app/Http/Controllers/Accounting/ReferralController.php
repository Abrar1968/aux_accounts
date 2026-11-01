<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\UpdateReferralRequest;
use App\Http\Resources\RecordResource;
use App\Models\Referral;
use App\Services\Accounting\ReferralService;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals)
    {
    }

    public function index(Request $request, ListingService $list): Response
    {
        $page = $list->page('referrals', $request->query());
        $page->through(fn ($r) => (new RecordResource($r))->resolve() + ['status' => $this->referrals->status($r), 'project' => $r->project?->only('id', 'code', 'name')]);

        return Inertia::render('Accounting/Referrals/Index', ['referrals' => $page]);
    }

    public function update(UpdateReferralRequest $request, Referral $referral)
    {
        $d = $request->validated();
        $this->referrals->recordPayment($referral, $d['paid_on'], (float) $d['paid_amount'], $d['notes'] ?? null);

        return $this->toast("Referral {$referral->code} payment recorded.");
    }
}
