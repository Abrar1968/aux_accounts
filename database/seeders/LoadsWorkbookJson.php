<?php

namespace Database\Seeders;

trait LoadsWorkbookJson
{
    /** @return list<array<string,mixed>> */
    protected function json(string $name): array
    {
        $path = database_path('seeders/data/'.$name.'.json');

        return is_file($path) ? json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : [];
    }

    protected function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    protected function str(mixed $v): ?string
    {
        return $v === null || $v === '' ? null : trim((string) $v);
    }
}
