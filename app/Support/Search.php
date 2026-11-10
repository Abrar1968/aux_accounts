<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Word-by-word LIKE search used by every list, picker and the global search: each word of the
 * query must match at least one column (any order, case-insensitive). Phone-like words also
 * match digits-only / normalised numbers ("01711-000111" finds 8801711000111).
 */
final class Search
{
    /** @param  list<string>  $columns */
    public static function apply(Builder $query, ?string $q, array $columns): Builder
    {
        foreach (self::words($q) as $word) {
            $variants = [$word];
            $digits = preg_replace('/\D+/', '', $word);
            if (strlen($digits) >= 4 && strlen($digits) >= strlen(preg_replace('/[\s\-+().]/', '', $word)) - 1) {
                $variants[] = ltrim(preg_replace('/^880/', '', $digits), '0');
            }
            $variants = array_values(array_unique(array_filter($variants, fn ($v) => $v !== '')));
            $query->where(function (Builder $w) use ($columns, $variants) {
                foreach ($columns as $col) {
                    foreach ($variants as $v) {
                        $w->orWhere($col, 'like', '%'.self::escape($v).'%');
                    }
                }
            });
        }

        return $query;
    }

    /** In-memory version of apply(): every word must occur in $text (case-insensitive, phone-aware). */
    public static function matches(string $text, ?string $q): bool
    {
        $hay = mb_strtolower($text);
        $hayDigits = preg_replace('/\D+/', '', $text);
        foreach (self::words($q) as $word) {
            if (str_contains($hay, mb_strtolower($word))) {
                continue;
            }
            $d = ltrim(preg_replace('/^880/', '', preg_replace('/\D+/', '', $word)), '0');
            if (strlen($d) < 4 || ! str_contains($hayDigits, $d)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public static function words(?string $q): array
    {
        $q = trim(mb_substr((string) $q, 0, 120));

        $words = [];
        foreach (preg_split('/\s+/u', $q) ?: [] as $w) {
            // "+880 1811-222333" is one phone number, not two words
            if ($w !== '' && $words && preg_match('/^[+\d\-().]+$/', $w) && preg_match('/^[+\d\-().]+$/', end($words))) {
                $words[count($words) - 1] .= $w;
            } elseif ($w !== '') {
                $words[] = $w;
            }
        }

        return array_slice($words, 0, 6);
    }

    private static function escape(string $v): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $v);
    }
}
