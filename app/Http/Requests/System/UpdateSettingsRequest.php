<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
            'settings.accounting\.fiscal_year_start' => ['sometimes', 'integer', 'between:1,12'],
        ];
    }
}
