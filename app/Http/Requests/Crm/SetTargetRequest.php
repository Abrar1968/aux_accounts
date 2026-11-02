<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class SetTargetRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner' => ['required', 'string', 'max:120'],
            'month' => ['required', 'date_format:Y-m'],
            'calls_target' => ['required', 'integer', 'min:0', 'max:100000'],
            'leads_target' => ['required', 'integer', 'min:0', 'max:100000'],
            'wins_target' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
