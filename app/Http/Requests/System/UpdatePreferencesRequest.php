<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', 'string'],
            'preferences.*.in_app' => ['boolean'],
            'preferences.*.push' => ['boolean'],
            'preferences.*.email' => ['boolean'],
        ];
    }
}
