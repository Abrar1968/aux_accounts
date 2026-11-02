<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreLiabilityRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'txn_date' => ['required', 'date', 'before_or_equal:today'],
            'liability_code' => ['required', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:255'],
            'increase' => ['nullable', 'numeric', 'min:0'],
            'decrease' => ['nullable', 'numeric', 'min:0'],
            'cash_impact' => ['required', 'numeric'],
            'counterparty' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
