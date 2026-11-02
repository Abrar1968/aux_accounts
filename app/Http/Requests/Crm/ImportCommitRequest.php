<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class ImportCommitRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'uuid'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['integer'],
        ];
    }
}
