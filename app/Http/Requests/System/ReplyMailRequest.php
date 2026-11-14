<?php

namespace App\Http\Requests\System;

use App\Services\System\EmailComposerService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Reply to an inbox message (route middleware: permission:email.inbox). */
class ReplyMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
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
            'to' => ['required', 'string', 'max:2000', $emails],
            'cc' => ['nullable', 'string', 'max:2000', $emails],
            'subject' => ['required', 'string', 'max:200'],
            'greeting' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'include_quote' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['body.required' => 'Write your reply.'];
    }
}
