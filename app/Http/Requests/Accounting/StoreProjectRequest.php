<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
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
            'customer' => ['nullable', 'string', 'max:190'],
            'kind' => ['required', 'in:project,international,lead'],
            'start_date' => ['required', 'date'],
            'est_delivery_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'actual_delivery_date' => ['nullable', 'date', 'before_or_equal:today'],
            'revenue_method' => ['required', 'in:Point in Time,Over Time,Lead / No Revenue'],
            'base_value' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'change_orders' => ['nullable', 'numeric', 'decimal:0,2'],
            'referrer' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
