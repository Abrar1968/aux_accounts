<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collaborator_id' => ['required', 'exists:collaborators,id'],
            'package_id' => ['required', 'exists:partnership_packages,id'],
            'effective_from' => ['nullable', 'date', 'required_if:status,Active'],
            'effective_through' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', 'in:Draft,Active,Inactive'],
            'earning_method' => ['required', 'in:Direct Amount,Share of Sales'],
            'share_percent' => ['nullable', 'numeric', 'between:0,1', 'required_if:earning_method,Share of Sales'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
