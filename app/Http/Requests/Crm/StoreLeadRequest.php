<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:40'],
            'lead_date' => ['nullable', 'date', 'before_or_equal:today'],
            'business_type' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'service' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'outcome' => ['nullable', 'exists:lead_outcomes,name'],
            'owner' => ['nullable', 'string', 'max:120'],
            'quoted_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
