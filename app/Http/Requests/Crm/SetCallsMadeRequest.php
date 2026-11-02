<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class SetCallsMadeRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'calls_made' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
