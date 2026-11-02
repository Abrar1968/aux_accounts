<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreCallRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'call_uuid' => ['required', 'uuid'],
            'call_type' => ['required', 'in:Follow-up,Initial'],
            'outcome' => ['required', 'exists:lead_outcomes,name'],
            'caller' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'next_followup' => ['nullable', 'date'],
        ];
    }
}
