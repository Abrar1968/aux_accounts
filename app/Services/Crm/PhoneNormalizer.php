<?php

namespace App\Services\Crm;

/**
 * Leads!AB port. Bangladesh: 01XXXXXXXXX → 8801…, 1XXXXXXXXX → 8801…, 8801… kept;
 * international: +CC (8–15 digits) → digits. Spaces, hyphens and parentheses are ignored.
 */
class PhoneNormalizer
{
    public function normalize(string|int|null $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $p = preg_replace('/[ ()\-]/', '', trim((string) $phone));

        return match (true) {
            (bool) preg_match('/^\+[1-9]\d{7,14}$/', $p) => substr($p, 1),
            (bool) preg_match('/^01[3-9]\d{8}$/', $p) => '88'.$p,
            (bool) preg_match('/^1[3-9]\d{8}$/', $p) => '880'.$p,
            (bool) preg_match('/^8801[3-9]\d{8}$/', $p) => $p,
            default => null,
        };
    }

    public function isValid(string|int|null $phone): bool
    {
        return $this->normalize($phone) !== null;
    }

    public function whatsappUrl(?string $normalized): ?string
    {
        return $normalized && preg_match('/^[1-9]\d{7,14}$/', $normalized) ? 'https://wa.me/'.$normalized : null;
    }
}
