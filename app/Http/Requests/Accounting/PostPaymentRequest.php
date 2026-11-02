<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class PostPaymentRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['nullable', 'string', 'max:60'],
            'account' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'request_id' => ['required', 'uuid'],
        ];
    }
}
