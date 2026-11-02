<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ReconcileRequest;
use App\Http\Requests\Accounting\StoreBankAccountRequest;
use App\Models\BankAccount;
use App\Services\Banking\BankingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankingController extends Controller
{
    public function __construct(private readonly BankingService $banking) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Accounting/Banking/Index', ['balances' => $this->banking->balances($request->query('as_of'))]);
    }

    /** {account} = id or "unassigned". */
    public function show(Request $request, string $account): Response
    {
        $model = $account === 'unassigned' ? null : BankAccount::query()->findOrFail((int) $account);
        $from = $request->query('from', now()->startOfMonth()->subMonths(2)->toDateString());
        $to = $request->query('to', now()->toDateString());

        return Inertia::render('Accounting/Banking/Show', [
            'account' => $model, 'from' => $from, 'to' => $to,
            'ledger' => $this->banking->ledger($model, $from, $to),
            'reconciliations' => $model ? $this->banking->history($model) : [],
        ]);
    }

    public function store(StoreBankAccountRequest $request)
    {
        $a = $this->banking->create($request->validated());

        return $this->toast("{$a->name} added. Matching movements are allocated automatically.");
    }

    public function update(StoreBankAccountRequest $request, BankAccount $account)
    {
        $this->banking->update($account, $request->validated());

        return $this->toast("{$account->name} updated.");
    }

    public function reconcile(ReconcileRequest $request, BankAccount $account)
    {
        $r = $this->banking->reconcile($account, $request->validated('statement_date'), (float) $request->validated('statement_balance'), $request->validated('notes'));

        return $r->status === 'matched'
            ? $this->toast("{$account->name} reconciled — book and statement agree.")
            : $this->toast("{$account->name}: difference of ৳".number_format((float) $r->difference, 2).' recorded for review.', 'warning', 'Difference found');
    }
}
