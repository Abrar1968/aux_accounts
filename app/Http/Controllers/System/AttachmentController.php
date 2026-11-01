<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Services\Media\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments)
    {
    }

    public function store(StoreAttachmentRequest $request)
    {
        $owner = $this->attachments->resolve($request->validated('attachable_type'), (int) $request->validated('attachable_id'));
        $a = $this->attachments->store($owner, $request->file('file'));

        return $this->toast("{$a->original_name} attached.");
    }

    /** Signed, time-limited download (route middleware: signed). */
    public function show(Request $request, Attachment $attachment)
    {
        $path = $request->boolean('thumb') && $attachment->thumb_path ? $attachment->thumb_path : $attachment->path;

        return Storage::disk($attachment->disk)->response($path, $attachment->original_name, ['Content-Type' => $attachment->mime, 'Cache-Control' => 'private, max-age=1800']);
    }

    public function link(Attachment $attachment)
    {
        return response()->json(['url' => $this->attachments->signedUrl($attachment), 'thumb' => $attachment->thumb_path ? $this->attachments->signedUrl($attachment, true) : null]);
    }

    public function destroy(Attachment $attachment)
    {
        $this->attachments->delete($attachment);

        return $this->toast('Attachment removed.');
    }
}
