<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            'role_id' => ['sometimes', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
