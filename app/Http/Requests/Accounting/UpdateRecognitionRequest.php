<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecognitionRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'date'],
            'ending_progress' => ['nullable', 'numeric', 'between:0,1'],
            'override_amount' => ['nullable', 'numeric', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
