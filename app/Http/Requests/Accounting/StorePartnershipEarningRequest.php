<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnershipEarningRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gross_sales' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'direct_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
