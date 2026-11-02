<?php

namespace App\Services\System;

use App\Exceptions\Domain\DomainException;
use App\Models\Project;
use App\Services\Media\ImageService;
use App\Services\Shared\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Letterhead assets (new feature): company logo + accounts seal (Settings) and a client signature
 * per project. Images are processed with GD into PNG (print/screen) and JPEG (in-house PDF writer)
 * and kept on the private local disk — no storage:link needed on shared hosting. They are served
 * through BrandingController (logo is public for emails; seal and signatures need a login).
 */
class BrandingService
{
    public const ASSETS = ['logo' => 'Company logo', 'seal' => 'Accounts seal / signature'];

    public function __construct(
        private readonly ImageService $images,
        private readonly SettingsService $settings,
    ) {}

    public function upload(string $asset, UploadedFile $file): array
    {
        if (! isset(self::ASSETS[$asset])) {
            throw new DomainException('Unknown branding asset.');
        }
        $old = $this->settings->get('branding.'.$asset);
        $meta = $this->store($file, 'branding', $asset);
        $this->settings->set('branding.'.$asset, $meta, 'branding');
        $this->settings->flush();
        $this->deleteFiles($old);

        return $meta;
    }

    public function remove(string $asset): void
    {
        $this->deleteFiles($this->settings->get('branding.'.$asset));
        $this->settings->set('branding.'.$asset, null, 'branding');
        $this->settings->flush();
    }

    public function setClientSignature(Project $project, ?UploadedFile $file): Project
    {
        $old = $project->client_signature;
        $project->client_signature = $file ? $this->store($file, 'signatures', 'project-'.$project->id) : null;
        $project->save();
        $this->deleteFiles($old);

        return $project;
    }

    /** Asset file for the controller. @return array{path:string, mime:string}|null */
    public function file(string $asset, string $format): ?array
    {
        $meta = $this->settings->get('branding.'.$asset);

        return $this->pick($meta, $format);
    }

    /** @return array{path:string, mime:string}|null */
    public function signatureFile(Project $project, string $format): ?array
    {
        return $this->pick($project->client_signature, $format);
    }

    /**
     * Shared Inertia prop: letterhead text + asset URLs (cache-busted) for PrintLayout and PDFs.
     *
     * @return array<string, mixed>
     */
    public function brand(): array
    {
        $asset = function (string $name) {
            $m = $this->settings->get('branding.'.$name);

            return is_array($m) ? ['png' => route('branding.show', [$name, 'png']).'?v='.$m['v'], 'jpg' => route('branding.show', [$name, 'jpg']).'?v='.$m['v'],
                'width' => $m['width'], 'height' => $m['height']] : null;
        };

        return [
            'company' => $this->settings->get('company.name'), 'address' => $this->settings->get('company.address'),
            'phone' => $this->settings->get('company.phone'), 'website' => $this->settings->get('company.website'),
            'email' => config('mail.from.address'), 'signature_title' => $this->settings->get('company.signature_title'),
            'logo' => $asset('logo'), 'seal' => $asset('seal'),
        ];
    }

    /** Client signature URLs for a project's documents (null when none uploaded). */
    public function signatureUrls(Project $project): ?array
    {
        $m = $project->client_signature;

        return is_array($m) ? ['png' => route('projects.signature', [$project, 'png']).'?v='.$m['v'], 'jpg' => route('projects.signature', [$project, 'jpg']).'?v='.$m['v'],
            'width' => $m['width'], 'height' => $m['height'], 'name' => $project->customer ?: $project->name] : null;
    }

    /** Absolute public logo URL for emails, or null. */
    public function emailLogoUrl(): ?string
    {
        return $this->brand()['logo']['png'] ?? null;
    }

    private function store(UploadedFile $file, string $dir, string $name): array
    {
        $v = $this->images->brandVariants($file->getRealPath(), (string) $file->getMimeType());
        $base = $dir.'/'.$name.'-'.Str::lower(Str::random(8));
        Storage::disk('local')->put($base.'.png', $v['png']);
        Storage::disk('local')->put($base.'.jpg', $v['jpg']);

        return ['png' => $base.'.png', 'jpg' => $base.'.jpg', 'width' => $v['width'], 'height' => $v['height'], 'v' => now()->timestamp];
    }

    private function pick(mixed $meta, string $format): ?array
    {
        if (! is_array($meta) || ! in_array($format, ['png', 'jpg'], true) || ! Storage::disk('local')->exists($meta[$format])) {
            return null;
        }

        return ['path' => Storage::disk('local')->path($meta[$format]), 'mime' => $format === 'png' ? 'image/png' : 'image/jpeg'];
    }

    private function deleteFiles(mixed $meta): void
    {
        if (is_array($meta)) {
            Storage::disk('local')->delete(array_filter([$meta['png'] ?? null, $meta['jpg'] ?? null]));
        }
    }
}
