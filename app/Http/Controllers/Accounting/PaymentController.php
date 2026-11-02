<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\DeleteWithReasonRequest;
use App\Http\Requests\Accounting\StorePaymentRequest;
use App\Http\Requests\Accounting\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\CustomerPayment;
use App\Services\Accounting\CustomerPaymentService;
use App\Services\Accounting\ReceivableService;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly CustomerPaymentService $payments) {}

    public function index(Request $request, ListingService $list): Response
    {
        return Inertia::render('Accounting/Payments/Index', [
            'payments' => PaymentResource::collection($list->page('payments', $request->query())),
            'filters' => $request->only(['q', 'type', 'from', 'to']),
        ]);
    }

    public function store(StorePaymentRequest $request)
    {
        $p = $this->payments->record($request->validated());

        return $this->toast("Payment {$p->code} saved (৳".number_format((float) $p->cash_received, 2).').');
    }

    public function destroy(DeleteWithReasonRequest $request, CustomerPayment $payment)
    {
        $this->payments->delete($payment, $request->validated('reason'));

        return $this->toast("Payment {$payment->code} deleted.");
    }

    /** JSON for the receivable picker. */
    public function receivables(Request $request, ReceivableService $receivables)
    {
        return response()->json($receivables->options($request->query('q')));
    }

    public function update(UpdatePaymentRequest $request, CustomerPayment $payment)
    {
        $this->payments->update($payment, $request->validated());

        return $this->toast("Payment {$payment->code} updated.");
    }
}
