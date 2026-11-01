<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['sometimes', 'string', 'max:40'],
            'business_type' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'service' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'outcome' => ['sometimes', 'exists:lead_outcomes,name'],
            'owner' => ['nullable', 'string', 'max:120'],
            'quoted_price' => ['nullable', 'numeric', 'min:0'],
            'followup_override' => ['nullable', 'date'],
            'meeting_at' => ['nullable', 'date'],
            'meeting_details' => ['nullable', 'string', 'max:255'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
