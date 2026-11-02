<?php

namespace App\Http\Requests\Accounting;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:190'],
            'customer' => ['nullable', 'string', 'max:190'],
            'start_date' => ['sometimes', 'date'],
            'est_delivery_date' => ['nullable', 'date'],
            'actual_delivery_date' => ['nullable', 'date', 'before_or_equal:today'],
            'revenue_method' => ['sometimes', 'in:Point in Time,Point in Time (Provisional),Point in Time + Change Order,Over Time,Lead / No Revenue'],
            'base_value' => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
            'change_orders' => ['nullable', 'numeric', 'decimal:0,2'],
            'referrer' => ['nullable', 'string', 'max:255', fn ($a, $v, $fail) => count(Project::parseReferrers($v)) > 10 ? $fail('At most 10 referrers per project.') : null],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
