<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paid_at' => ['required', 'date', 'before:tomorrow'],
            'receivable_type' => ['required', 'in:project,marketing,opening,partnership'],
            'receivable_id' => ['required', 'integer'],
            'cash_received' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'processing_fee' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'method' => ['nullable', 'string', 'max:60'],
            'account' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'request_id' => ['required', 'uuid'],
        ];
    }
}
