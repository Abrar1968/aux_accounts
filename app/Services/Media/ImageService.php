<?php

namespace App\Services\Media;

use App\Exceptions\Domain\DomainException;
use GdImage;

/**
 * GD-only image pipeline: validates, fixes EXIF orientation, resizes (max 1600 px), creates a
 * 320 px thumbnail and re-encodes (JPEG q82 / WebP q80 / PNG), which also strips metadata.
 */
class ImageService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MAX_EDGE = 1600;

    public const THUMB_EDGE = 320;

    /** Every raster format GD can decode (SVG/HEIC/TIFF are converted to PNG in the browser first). */
    public const MIMES = ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/x-ms-bmp', 'image/x-bmp', 'image/avif', 'image/x-tga', 'image/x-xbitmap', 'image/vnd.wap.wbmp'];

    /** For FormRequest `mimes:` rules. */
    public const EXTENSIONS = 'jpg,jpeg,jpe,jfif,pjpeg,png,webp,gif,bmp,avif,tga,xbm,wbmp';

    public function isImage(string $mime): bool
    {
        return in_array($mime, self::MIMES, true);
    }

    /** @return array{main:string, thumb:string, width:int, height:int, mime:string, ext:string} binary strings */
    public function process(string $path, string $mime): array
    {
        if (! extension_loaded('gd')) {
            throw new DomainException('The PHP GD extension is required for image uploads.');
        }
        if (filesize($path) > self::MAX_BYTES) {
            throw new DomainException('Images must be 10 MB or smaller.');
        }
        $src = match ($mime) {
            'image/jpeg', 'image/jpg', 'image/pjpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/gif' => @imagecreatefromgif($path),
            'image/bmp', 'image/x-ms-bmp', 'image/x-bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : false,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            'image/x-tga' => function_exists('imagecreatefromtga') ? @imagecreatefromtga($path) : false,
            'image/x-xbitmap' => @imagecreatefromxbm($path),
            'image/vnd.wap.wbmp' => @imagecreatefromwbmp($path),
            default => false,
        };
        if (! $src instanceof GdImage) { // wrong/unknown mime header: let GD sniff the bytes
            $src = @imagecreatefromstring((string) file_get_contents($path));
        }
        if (! $src instanceof GdImage) {
            throw new DomainException('Unsupported or corrupt image.');
        }
        if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true) && function_exists('exif_read_data')) {
            $src = $this->orient($src, (int) (@exif_read_data($path)['Orientation'] ?? 1));
        }
        $main = $this->fit($src, self::MAX_EDGE);
        $thumb = $this->fit($src, self::THUMB_EDGE);
        [$outMime, $ext] = $mime === 'image/png' ? ['image/png', 'png'] : (function_exists('imagewebp') && $mime === 'image/webp' ? ['image/webp', 'webp'] : ['image/jpeg', 'jpg']);
        $result = [
            'main' => $this->encode($main, $outMime), 'thumb' => $this->encode($thumb, $outMime),
            'width' => imagesx($main), 'height' => imagesy($main), 'mime' => $outMime, 'ext' => $ext,
        ];
        imagedestroy($src);

        return $result;
    }

    /**
     * Letterhead assets (logo, seal, signatures): transparent PNG for screen/print and a flattened
     * JPEG (white background) for the in-house PDF writer, which embeds JPEG only.
     *
     * @return array{png:string, jpg:string, width:int, height:int}
     */
    public function brandVariants(string $path, string $mime, int $edge = 800): array
    {
        $tmp = $this->process($path, $mime); // validates, orients, strips metadata
        $src = @imagecreatefromstring($tmp['main']);
        if (! $src instanceof GdImage) {
            throw new DomainException('Unsupported or corrupt image.');
        }
        $img = $this->fit($src, $edge);

        return ['png' => $this->encode($img, 'image/png'), 'jpg' => $this->encode($img, 'image/jpeg'), 'width' => imagesx($img), 'height' => imagesy($img)];
    }

    private function orient(GdImage $img, int $o): GdImage
    {
        $rotated = match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };

        return $rotated instanceof GdImage ? $rotated : $img;
    }

    public function fit(GdImage $img, int $edge): GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $edge / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    private function encode(GdImage $img, string $mime): string
    {
        ob_start();
        match ($mime) {
            'image/png' => imagepng($img, null, 6),
            'image/webp' => imagewebp($img, null, 80),
            default => imagejpeg($this->flatten($img), null, 82),
        };

        return (string) ob_get_clean();
    }

    private function flatten(GdImage $img): GdImage
    {
        $out = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopy($out, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));

        return $out;
    }
}
