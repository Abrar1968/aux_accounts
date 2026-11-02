<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\DeleteWithReasonRequest;
use App\Http\Requests\Accounting\PostPaymentRequest;
use App\Http\Requests\Accounting\StoreExpenseRequest;
use App\Http\Requests\Accounting\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\RecordResource;
use App\Models\ExpenseCategory;
use App\Models\ExpensePayment;
use App\Models\OperatingExpense;
use App\Services\Accounting\ExpenseService;
use App\Services\Accounting\PaymentPostingService;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(Request $request, ListingService $list): Response
    {
        return Inertia::render('Accounting/Expenses/Index', [
            'expenses' => ExpenseResource::collection($list->page('expenses', $request->query())),
            'categories' => ExpenseCategory::query()->orderBy('sort')->pluck('name'),
            'filters' => $request->only(['q', 'category', 'from', 'to']),
        ]);
    }

    public function show(OperatingExpense $expense): Response
    {
        $expense->load('payments', 'attachments');

        return Inertia::render('Accounting/Expenses/Show', [
            'expense' => new ExpenseResource($expense),
            'payments' => RecordResource::collection($expense->payments),
            'attachments' => RecordResource::collection($expense->attachments),
        ]);
    }

    public function store(StoreExpenseRequest $request)
    {
        $e = $this->expenses->create($request->validated());

        return $this->toast("Bill {$e->code} created. Record payments when cash is paid.");
    }

    public function update(UpdateExpenseRequest $request, OperatingExpense $expense)
    {
        $this->expenses->update($expense, $request->validated());

        return $this->toast("Bill {$expense->code} updated.");
    }

    /** Record Payment (AuxtechPayments.gs) — idempotent by request_id. */
    public function pay(PostPaymentRequest $request, OperatingExpense $expense, PaymentPostingService $posting)
    {
        $r = $posting->post('expense', $expense->id, $request->validated());

        return $this->toast(($r['action'] === 'already_saved' ? 'Already saved ' : 'SAVED ').$r['payment']->code.'. Remaining ৳'.number_format($r['remaining'], 2).'.');
    }

    public function destroy(DeleteWithReasonRequest $request, OperatingExpense $expense)
    {
        $this->expenses->delete($expense, $request->validated('reason'));

        return redirect('/accounting/expenses')->with('toast', ['type' => 'success', 'title' => 'Deleted', 'message' => "Bill {$expense->code} deleted."]);
    }

    public function reversePayment(DeleteWithReasonRequest $request, ExpensePayment $expensePayment, PaymentPostingService $posting)
    {
        $rev = $posting->reverse('expense', $expensePayment, $request->validated('reason'));

        return $this->toast("Payment reversed ({$rev->code}). The bill balance is restored.", 'success', 'Reversed');
    }
}
