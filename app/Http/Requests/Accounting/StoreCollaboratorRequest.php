<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollaboratorRequest extends FormRequest
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
            'status' => ['required', 'in:Draft,Active,Inactive'],
            'agreement_start' => ['nullable', 'date', 'required_if:status,Active'],
            'agreement_end' => ['nullable', 'date', 'after_or_equal:agreement_start'],
            'contact' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
