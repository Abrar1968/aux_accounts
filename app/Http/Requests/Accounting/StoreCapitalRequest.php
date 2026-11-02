<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreCapitalRequest extends FormRequest
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
            'investor_id' => ['required', 'exists:investors,id'],
            'type' => ['required', 'in:Capital contribution,Distribution,Share transfer / reclassification,Opening paid-in capital'],
            'cash_impact' => ['required', 'numeric'],
            'equity_impact' => ['required', 'numeric'],
            'counterparty' => ['nullable', 'string', 'max:120'],
            'ownership_impact' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
