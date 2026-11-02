<?php

namespace App\Http\Requests\System;

use App\Services\Media\ImageService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:*). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string'],
            'attachable_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:10240', 'mimes:'.ImageService::EXTENSIONS.',pdf'],
        ];
    }
}
