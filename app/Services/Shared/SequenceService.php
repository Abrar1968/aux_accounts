<?php

namespace App\Services\Shared;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free, concurrency-safe counters that reproduce workbook ID formats
 * (EXP-110, PAY-261013-044, PRJ-2610-019, MKT-2611-003, ADV-051 …).
 */
class SequenceService
{
    public function next(string $key, int $floor = 0): int
    {
        return DB::transaction(function () use ($key, $floor) {
            $seq = Sequence::query()->lockForUpdate()->firstOrCreate(['key' => $key], ['value' => 0]);
            $seq->value = max($seq->value, $floor) + 1;
            $seq->save();

            return $seq->value;
        });
    }

    public function bump(string $key, int $value): void
    {
        $seq = Sequence::query()->firstOrCreate(['key' => $key], ['value' => 0]);
        if ($value > $seq->value) {
            $seq->update(['value' => $value]);
        }
    }

    public function code(string $prefix, int $n, int $pad = 3, ?string $infix = null): string
    {
        return $prefix.'-'.($infix ? $infix.'-' : '').str_pad((string) $n, $pad, '0', STR_PAD_LEFT);
    }
}
