<?php

namespace App\Services\Media;

use App\Exceptions\Domain\DomainException;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Receipts / evidence on any record. Images go through GD; PDFs are stored as-is.
 * Files live on the private disk and are served through temporary signed URLs.
 */
class AttachmentService
{
    public const ATTACHABLE = [
        'expense' => \App\Models\OperatingExpense::class,
        'payment' => \App\Models\CustomerPayment::class,
        'project' => \App\Models\Project::class,
        'asset' => \App\Models\FixedAsset::class,
        'liability' => \App\Models\Liability::class,
        'capital' => \App\Models\CapitalTransaction::class,
        'payroll' => \App\Models\Payroll::class,
        'lead' => \App\Models\Lead::class,
    ];

    public function __construct(private readonly ImageService $images)
    {
    }

    public function resolve(string $type, int $id): Model
    {
        $class = self::ATTACHABLE[$type] ?? throw new DomainException('Attachments are not supported for '.$type);

        return $class::query()->findOrFail($id);
    }

    public function store(Model $owner, UploadedFile $file): Attachment
    {
        $mime = (string) $file->getMimeType();
        $disk = Storage::disk('local');
        $dir = 'attachments/'.now()->format('Y/m');
        $name = (string) Str::uuid();
        $meta = ['width' => null, 'height' => null, 'thumb_path' => null];

        if ($this->images->isImage($mime)) {
            $img = $this->images->process($file->getRealPath(), $mime);
            $path = "$dir/$name.{$img['ext']}";
            $thumb = "$dir/{$name}_thumb.{$img['ext']}";
            $disk->put($path, $img['main']);
            $disk->put($thumb, $img['thumb']);
            $meta = ['width' => $img['width'], 'height' => $img['height'], 'thumb_path' => $thumb];
            $mime = $img['mime'];
            $size = strlen($img['main']);
        } elseif ($mime === 'application/pdf') {
            if ($file->getSize() > ImageService::MAX_BYTES) {
                throw new DomainException('PDF must be 10 MB or smaller.');
            }
            $path = $disk->putFileAs($dir, $file, "$name.pdf");
            $size = (int) $file->getSize();
        } else {
            throw new DomainException('Only images (JPEG, PNG, WebP, GIF) and PDF files are allowed.');
        }

        return $owner->morphMany(Attachment::class, 'attachable')->create($meta + [
            'disk' => 'local', 'path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime' => $mime, 'size' => $size, 'uploaded_by' => Auth::id(),
        ]);
    }

    public function signedUrl(Attachment $a, bool $thumb = false): string
    {
        return URL::temporarySignedRoute('attachments.show', now()->addMinutes(30), ['attachment' => $a->id, 'thumb' => $thumb ? 1 : 0]);
    }

    public function delete(Attachment $a): void
    {
        Storage::disk($a->disk)->delete(array_filter([$a->path, $a->thumb_path]));
        $a->delete();
    }
}
