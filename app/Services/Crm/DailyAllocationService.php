<?php

namespace App\Services\Crm;

use App\Exceptions\Domain\DomainException;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Services\Shared\SettingsService;
use Carbon\CarbonImmutable;

/**
 * Daily lead slots (allocateDay_). In a database there are no empty rows to reserve, so slots
 * become a daily target: the Today page shows `created / target` and the remaining quick-entry rows.
 */
class DailyAllocationService
{
    public function __construct(
        private readonly LeadRepositoryInterface $leads,
        private readonly SettingsService $settings,
    ) {
    }

    public function target(): int
    {
        $t = (int) $this->settings->get('crm.daily_slots');
        if ($t < 1 || $t > 500) {
            throw new DomainException('Daily lead slots must be an integer 1–500');
        }

        return $t;
    }

    public function setTarget(int $slots): void
    {
        if ($slots < 1 || $slots > 500) {
            throw new DomainException('Daily lead slots must be an integer 1–500');
        }
        $this->settings->set('crm.daily_slots', $slots, 'crm');
    }

    /** @return array{date:string, target:int, created:int, remaining:int} */
    public function progress(?CarbonImmutable $day = null): array
    {
        $day ??= CarbonImmutable::now(config('app.timezone'));
        $created = $this->leads->query()->where('lead_date', $day->toDateString())->count();
        $target = $this->target();

        return ['date' => $day->toDateString(), 'target' => $target, 'created' => $created, 'remaining' => max(0, $target - $created)];
    }
}
