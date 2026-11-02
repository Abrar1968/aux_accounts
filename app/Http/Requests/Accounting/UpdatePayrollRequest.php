<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gross_salary' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
