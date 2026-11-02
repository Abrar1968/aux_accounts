<?php

namespace App\Http\Requests\Accounting;

use App\Services\Media\ImageService;
use Illuminate\Foundation\Http\FormRequest;

class ClientSignatureRequest extends FormRequest
{
    /** Permissions are enforced by route middleware (permission:accounting.projects.update). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['client_signature' => ['required', 'file', 'max:10240', 'mimes:'.ImageService::EXTENSIONS]];
    }

    public function messages(): array
    {
        return ['client_signature.mimes' => 'Upload the signature as an image (JPG, PNG, WebP, GIF, BMP or AVIF).'];
    }
}
