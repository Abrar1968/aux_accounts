<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdvanceRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'txn_date' => ['required', 'date', 'before_or_equal:today'],
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', 'in:Disbursement,Recovery'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'related_salary_period' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
