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

    /** Accent palettes defined in resources/css/app.css ([data-accent]). */
    public const ACCENTS = ['indigo', 'blue', 'violet', 'emerald', 'teal', 'rose', 'amber'];

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
        if ($asset === 'logo') { // browser tab icon + home-screen icon from the logo
            $png = (string) Storage::disk('local')->get($meta['png']);
            foreach (['favicon' => 64, 'touch' => 180] as $key => $edge) {
                $meta[$key] = preg_replace('/\.png$/', "-{$key}.png", $meta['png']);
                Storage::disk('local')->put($meta[$key], $this->images->squareIcon($png, $edge));
            }
        }
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
        return $this->pick($this->meta($asset), $format);
    }

    /**
     * Uploaded asset (Settings → Branding) or the bundled company default in resources/branding
     * (Auxtech logo + accounts signature), so documents are branded out of the box.
     */
    public function meta(string $asset): ?array
    {
        $m = $this->settings->get('branding.'.$asset);
        if (is_array($m)) {
            return $m;
        }
        $files = ['logo' => ['png' => 'logo.png', 'jpg' => 'logo.jpg', 'favicon' => 'logo-favicon.png', 'touch' => 'logo-touch.png', 'mark' => 'logo-mark.png'],
            'seal' => ['png' => 'seal.png', 'jpg' => 'seal.jpg']][$asset] ?? null;
        $dir = resource_path('branding');
        if (! $files || ! is_file($dir.'/'.$files['png'])) {
            return null;
        }
        [$w, $h] = getimagesize($dir.'/'.$files['png']);

        return array_map(fn ($f) => $dir.'/'.$f, $files) + ['width' => $w, 'height' => $h, 'v' => filemtime($dir.'/'.$files['png']), 'default' => true];
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
            $m = $this->meta($name);

            return is_array($m) ? ['png' => route('branding.show', [$name, 'png']).'?v='.$m['v'], 'jpg' => route('branding.show', [$name, 'jpg']).'?v='.$m['v'],
                'width' => $m['width'], 'height' => $m['height'], 'default' => ! empty($m['default'])] : null;
        };

        return [
            'company' => $this->settings->get('company.name'), 'address' => $this->settings->get('company.address'),
            'phone' => $this->settings->get('company.phone'), 'website' => $this->settings->get('company.website'),
            'email' => config('mail.from.address'), 'signature_title' => $this->settings->get('company.signature_title'),
            'signature_name' => $this->settings->get('company.signature_name'),
            'logo' => $asset('logo'), 'seal' => $asset('seal'),
            'favicon' => $this->faviconUrl(),
            'mark' => $this->meta('logo') ? $this->faviconUrl(isset($this->meta('logo')['mark']) ? 'mark' : 'touch') : null,
            'accent' => $this->accent(),
        ];
    }

    /** Client signature URLs for a project's documents (null when none uploaded). */
    public function signatureUrls(Project $project): ?array
    {
        $m = $project->client_signature;

        return is_array($m) ? ['png' => route('projects.signature', [$project, 'png']).'?v='.$m['v'], 'jpg' => route('projects.signature', [$project, 'jpg']).'?v='.$m['v'],
            'width' => $m['width'], 'height' => $m['height'], 'name' => $project->customer ?: $project->name] : null;
    }

    public function accent(): string
    {
        $a = (string) ($this->settings->get('branding.accent') ?: 'amber'); // amber = "Command glass" house colour

        return in_array($a, self::ACCENTS, true) ? $a : 'amber';
    }

    public function setAccent(string $accent): void
    {
        if (! in_array($accent, self::ACCENTS, true)) {
            throw new DomainException('Unknown accent colour.');
        }
        $this->settings->set('branding.accent', $accent, 'branding');
        $this->settings->flush();
    }

    /** Public letterhead for the sign-in pages (no seal / private data). */
    public function guestBrand(): array
    {
        $logo = $this->meta('logo');

        return ['company' => $this->settings->get('company.name'), 'app' => config('app.name'),
            'logo' => $logo ? route('branding.show', ['logo', 'png']).'?v='.$logo['v'] : null,
            'mark' => $logo ? $this->faviconUrl(isset($logo['mark']) ? 'mark' : 'touch') : null];
    }

    /** Logo-derived browser icon (cache-busted), or the default /favicon.ico. */
    public function faviconUrl(string $kind = 'favicon'): string
    {
        $m = $this->meta('logo');

        return is_array($m) && isset($m[$kind]) ? route('branding.icon', $kind).'?v='.$m['v'] : '/favicon.ico';
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
        if (! is_array($meta) || ! in_array($format, ['png', 'jpg', 'favicon', 'touch', 'mark'], true) || ! isset($meta[$format])) {
            return null;
        }
        $path = ! empty($meta['default']) ? $meta[$format] : Storage::disk('local')->path($meta[$format]);

        return is_file($path) ? ['path' => $path, 'mime' => $format === 'jpg' ? 'image/jpeg' : 'image/png'] : null;
    }

    private function deleteFiles(mixed $meta): void
    {
        if (is_array($meta)) {
            Storage::disk('local')->delete(array_filter([$meta['png'] ?? null, $meta['jpg'] ?? null, $meta['favicon'] ?? null, $meta['touch'] ?? null]));
        }
    }
}
