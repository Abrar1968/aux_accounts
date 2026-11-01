<?php

namespace App\Support;

/**
 * BDT money helpers. Values are normalised through integer paisa so that comparisons and
 * sums never drift (the workbook rounds to 2 dp at the same points).
 */
final class Money
{
    public static function paisa(float|int|string|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    public static function round(float|int|string|null $amount): float
    {
        return self::paisa($amount) / 100;
    }

    public static function sum(iterable $values): float
    {
        $p = 0;
        foreach ($values as $v) {
            $p += self::paisa($v);
        }

        return $p / 100;
    }

    public static function hasAtMostTwoDecimals(float $amount): bool
    {
        return abs($amount * 100 - round($amount * 100)) <= 0.00001;
    }

    public static function format(float|int|string|null $amount): string
    {
        $v = self::round($amount);

        return ($v < 0 ? '-' : '').'৳'.number_format(abs($v), 2);
    }
}
