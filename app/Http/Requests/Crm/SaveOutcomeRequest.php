<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class SaveOutcomeRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'stage' => ['required', 'string'],
            'score' => ['required', 'integer', 'between:0,100'],
            'followup_days' => ['required', 'integer', 'between:0,365'],
        ];
    }
}
