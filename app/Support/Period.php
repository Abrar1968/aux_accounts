<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Immutable calendar-month value object. A period is identified by its first day (Y-m-01),
 * exactly like the workbook's EOMONTH(x,-1)+1 convention.
 */
final class Period
{
    private function __construct(public readonly CarbonImmutable $start)
    {
    }

    public static function of(string|DateTimeInterface $value): self
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }
        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)->setTimezone(config('app.timezone'))
            : CarbonImmutable::parse($value, config('app.timezone'));

        return new self($date->startOfMonth()->startOfDay());
    }

    public static function current(): self
    {
        return self::of(CarbonImmutable::now(config('app.timezone')));
    }

    public static function tryOf(mixed $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return self::of($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function key(): string
    {
        return $this->start->format('Y-m');
    }

    public function date(): string
    {
        return $this->start->toDateString();
    }

    /** First day of the next month — the workbook's EDATE(m,1) cut-off. */
    public function cut(): string
    {
        return $this->start->addMonth()->toDateString();
    }

    public function end(): string
    {
        return $this->start->endOfMonth()->toDateString();
    }

    public function label(): string
    {
        return $this->start->format('M-Y');
    }

    public function next(): self
    {
        return new self($this->start->addMonth());
    }

    public function previous(): self
    {
        return new self($this->start->subMonth());
    }

    public function addMonths(int $n): self
    {
        return new self($this->start->addMonths($n));
    }

    public function lt(self $o): bool
    {
        return $this->start->lt($o->start);
    }

    public function lte(self $o): bool
    {
        return $this->start->lte($o->start);
    }

    public function equals(self $o): bool
    {
        return $this->key() === $o->key();
    }

    /** @return list<self> inclusive range */
    public static function range(self $from, self $to): array
    {
        $out = [];
        for ($p = $from; $p->lte($to); $p = $p->next()) {
            $out[] = $p;
        }

        return $out;
    }

    public function __toString(): string
    {
        return $this->date();
    }
}
