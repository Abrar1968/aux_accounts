<?php

namespace App\Http\Requests\System;

use App\Services\Media\ImageService;
use App\Services\System\BrandingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadBrandingRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:settings.manage). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset' => ['required', Rule::in(array_keys(BrandingService::ASSETS))],
            'image' => ['required', 'file', 'max:10240', 'mimes:'.ImageService::EXTENSIONS],
        ];
    }

    public function messages(): array
    {
        return ['image.mimes' => 'Upload an image (JPG, PNG, WebP, GIF, BMP or AVIF).'];
    }
}
