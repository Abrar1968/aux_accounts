<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\ReceivableService;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableController extends Controller
{
    public function __invoke(Request $request, ReceivableService $receivables): Response
    {
        $p = $request->query('month') ? Period::of($request->query('month')) : Period::current();

        return Inertia::render('Accounting/Receivables', [
            'month' => $p->key(),
            'balances' => $receivables->balancesAt($p->cut())->filter(fn ($r) => $r['ar'] > 0 || $r['advance'] > 0)->values(),
            'aging' => $receivables->aging(min($p->end(), now()->toDateString())),
        ]);
    }

    public function statement(string $type, int $id, ReceivableService $receivables): Response
    {
        return Inertia::render('Accounting/CustomerStatement', ['statement' => $receivables->statement($type, $id)]);
    }
}
