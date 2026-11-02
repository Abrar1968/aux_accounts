<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'designation' => ['nullable', 'string', 'max:120'],
            'base_salary' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'left_on' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'phone' => ['nullable', 'string', 'max:40'],
            'bank_account' => ['nullable', 'string', 'max:120'],
        ];
    }
}
