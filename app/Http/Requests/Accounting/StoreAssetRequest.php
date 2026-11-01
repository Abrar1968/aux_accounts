<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'category' => ['nullable', 'string', 'max:120'],
            'acquisition_date' => ['required', 'date'],
            'in_service_date' => ['required', 'date', 'after_or_equal:acquisition_date'],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'residual_value' => ['nullable', 'numeric', 'min:0', 'lte:cost'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:600'],
            'opening_accumulated_dep' => ['nullable', 'numeric', 'min:0'],
            'cash_paid' => ['nullable', 'numeric', 'min:0'],
            'cash_payment_date' => ['nullable', 'date', 'required_with:cash_paid'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
