<?php

namespace App\Http\Requests\Accounting;

use App\Models\Project;
use App\Services\Media\ImageService;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'customer' => ['nullable', 'string', 'max:190'],
            'kind' => ['required', 'in:project,international,lead'],
            'start_date' => ['required', 'date'],
            'est_delivery_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'actual_delivery_date' => ['nullable', 'date', 'before_or_equal:today'],
            'revenue_method' => ['required', 'in:Point in Time,Over Time,Lead / No Revenue'],
            'base_value' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'change_orders' => ['nullable', 'numeric', 'decimal:0,2'],
            'referrer' => ['nullable', 'string', 'max:255', fn ($a, $v, $fail) => count(Project::parseReferrers($v)) > 10 ? $fail('At most 10 referrers per project.') : null],
            'notes' => ['nullable', 'string', 'max:5000'],
            'client_signature' => ['nullable', 'file', 'max:10240', 'mimes:'.ImageService::EXTENSIONS],
        ];
    }
}
