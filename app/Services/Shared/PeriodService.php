<?php

namespace App\Services\Shared;

use App\Events\PeriodClosed;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\PeriodLockedException;
use App\Models\Period as PeriodModel;
use App\Repositories\Contracts\PeriodRepositoryInterface;
use App\Support\Period;
use Illuminate\Support\Collection;

/**
 * Reporting months (Settings!A16:D) + period close/lock (new control).
 */
class PeriodService
{
    public function __construct(
        private readonly PeriodRepositoryInterface $periods,
        private readonly SettingsService $settings,
    ) {
    }

    public function modelStart(): Period
    {
        return Period::of($this->settings->get('accounting.model_start'));
    }

    /** Creates every missing month from model start through $through (idempotent). */
    public function ensureThrough(Period $through): int
    {
        $created = 0;
        foreach (Period::range($this->modelStart(), $through) as $p) {
            $row = PeriodModel::query()->firstOrCreate(['month' => $p->date()], ['status' => 'open']);
            $created += $row->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /** @return Collection<int, PeriodModel> */
    public function list(): Collection
    {
        return PeriodModel::query()->orderBy('month')->get();
    }

    public function isLocked(string|\DateTimeInterface|null $date): bool
    {
        if ($date === null || $date === '') {
            return false;
        }

        return PeriodModel::query()->where('month', Period::of($date)->date())->where('status', 'closed')->exists();
    }

    /** Throws when any of the given business dates falls in a closed month. */
    public function assertOpen(string|\DateTimeInterface|null ...$dates): void
    {
        foreach ($dates as $d) {
            if ($this->isLocked($d)) {
                throw new PeriodLockedException('Period '.Period::of($d)->label().' is closed. Ask an admin to reopen it.');
            }
        }
    }

    public function close(Period $p, ?int $userId, ?string $notes = null): PeriodModel
    {
        $row = PeriodModel::query()->firstOrCreate(['month' => $p->date()]);
        if ($row->status === 'closed') {
            throw new DomainException($p->label().' is already closed.');
        }
        $row->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $userId, 'notes' => $notes]);
        event(new PeriodClosed($row));

        return $row;
    }

    public function reopen(Period $p, string $reason): PeriodModel
    {
        $row = PeriodModel::query()->where('month', $p->date())->firstOrFail();
        $row->update(['status' => 'open', 'closed_at' => null, 'closed_by' => null, 'notes' => 'Reopened: '.$reason]);

        return $row;
    }
}
