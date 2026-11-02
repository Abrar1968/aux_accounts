<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\UploadBrandingRequest;
use App\Services\System\BrandingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Logo / accounts seal upload + delivery (images live on the private disk). */
class BrandingController extends Controller
{
    public function __construct(private readonly BrandingService $branding) {}

    /** Logo is public (used in emails); the seal requires a signed-in user. */
    public function show(Request $request, string $asset, string $format): BinaryFileResponse
    {
        abort_unless($asset === 'logo' || $request->user(), 403);
        $file = $this->branding->file($asset, $format) ?? abort(404);

        return response()->file($file['path'], ['Content-Type' => $file['mime'], 'Cache-Control' => ($asset === 'logo' ? 'public' : 'private').', max-age=31536000, immutable']);
    }

    /** Public favicon / apple-touch-icon generated from the logo. */
    public function icon(string $kind): BinaryFileResponse
    {
        $file = $this->branding->file('logo', $kind) ?? abort(404);

        return response()->file($file['path'], ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    public function store(UploadBrandingRequest $request)
    {
        $this->branding->upload($request->validated('asset'), $request->file('image'));

        return $this->toast(BrandingService::ASSETS[$request->validated('asset')].' updated — used on every invoice and PDF.');
    }

    public function destroy(string $asset)
    {
        abort_unless(isset(BrandingService::ASSETS[$asset]), 404);
        $this->branding->remove($asset);

        return $this->toast(BrandingService::ASSETS[$asset].' removed.');
    }
}
