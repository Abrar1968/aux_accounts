<?php

namespace App\Http\Requests\System;

use App\Services\System\EmailComposerService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Preview + send share these rules; `to` is required only when sending. */
class ComposeEmailRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:email.send). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sending = $this->routeIs('email.send');
        $emails = function (string $attr, mixed $value, Closure $fail) {
            $list = app(EmailComposerService::class)->addresses((string) $value);
            if (count($list) > 20) {
                $fail('Send to at most 20 addresses at once.');
            }
            foreach ($list as $e) {
                if (! filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $fail("\"{$e}\" is not a valid email address.");
                }
            }
        };

        return [
            'template' => ['required', Rule::in(array_keys(EmailComposerService::TEMPLATES))],
            'record_ref' => ['nullable', 'string', 'max:80'],
            'to' => [$sending ? 'required' : 'nullable', 'string', 'max:2000', $emails],
            'cc' => ['nullable', 'string', 'max:2000', $emails],
            'subject' => [$sending ? 'required' : 'nullable', 'string', 'max:200'],
            'greeting' => ['nullable', 'string', 'max:200'],
            'intro' => [$sending ? 'required' : 'nullable', 'string', 'max:5000'],
            'closing' => ['nullable', 'string', 'max:2000'],
            'details' => ['array', 'max:30'],
            'details.*.label' => ['nullable', 'string', 'max:120'],
            'details.*.value' => ['nullable', 'string', 'max:500'],
            'details.*.on' => ['boolean'],
            'include_table' => ['boolean'],
            'table' => ['nullable', 'array'],
            'table.columns' => ['array', 'max:8'],
            'table.columns.*' => ['string', 'max:40'],
            'table.rows' => ['array', 'max:300'],
            'table.rows.*' => ['array', 'max:8'],
            'table.rows.*.*' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return ['to.required' => 'Enter at least one recipient email.', 'intro.required' => 'Write the message.'];
    }
}
