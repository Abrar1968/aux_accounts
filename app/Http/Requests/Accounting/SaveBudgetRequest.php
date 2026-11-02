<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class SaveBudgetRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'budgets' => ['required', 'array'],
            'budgets.*' => ['nullable', 'numeric', 'min:-9999999999', 'max:9999999999'],
        ];
    }
}
