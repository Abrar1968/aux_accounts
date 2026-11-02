<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'period' => ['required', 'date'],
            'gross_salary' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
