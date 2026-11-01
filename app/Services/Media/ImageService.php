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

    public function isImage(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
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
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };
        if (! $src instanceof GdImage) {
            throw new DomainException('Unsupported or corrupt image.');
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
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
