<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class ConvertLeadRequest extends FormRequest
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
            'base_value' => ['nullable', 'numeric', 'min:0'],
            'kind' => ['nullable', 'in:project,international'],
            'start_date' => ['nullable', 'date'],
        ];
    }
}
