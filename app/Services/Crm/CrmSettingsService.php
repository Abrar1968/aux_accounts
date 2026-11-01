<?php

namespace App\Services\Crm;

use App\Exceptions\Domain\DomainException;
use App\Models\CrmOption;
use App\Models\LeadOutcome;
use App\Repositories\Contracts\CrmOptionRepositoryInterface;
use App\Repositories\Contracts\LeadOutcomeRepositoryInterface;

/** CRM Settings tab: outcome map and option lists. */
class CrmSettingsService
{
    public const TYPES = ['service', 'source', 'owner', 'business_type', 'industry'];

    public function __construct(
        private readonly LeadOutcomeRepositoryInterface $outcomes,
        private readonly CrmOptionRepositoryInterface $options,
    ) {
    }

    public function all(): array
    {
        $lists = [];
        foreach (self::TYPES as $t) {
            $lists[$t] = $this->options->query()->where('type', $t)->where('is_active', true)->orderBy('sort')->pluck('value')->all();
        }

        return ['outcomes' => $this->outcomes->all()->values()->all(), 'lists' => $lists];
    }

    public function saveOutcome(array $data): LeadOutcome
    {
        if (! in_array($data['stage'], ['New', 'Contacted', 'Interested', 'Meeting', 'Proposal', 'Negotiation', 'Won', 'Lost'], true)) {
            throw new DomainException('Unknown stage.');
        }

        return LeadOutcome::query()->updateOrCreate(['name' => $data['name']], $data);
    }

    public function addOption(string $type, string $value): CrmOption
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new DomainException('Unknown list.');
        }

        return CrmOption::query()->updateOrCreate(['type' => $type, 'value' => trim($value)], ['is_active' => true,
            'sort' => (int) CrmOption::query()->where('type', $type)->max('sort') + 1]);
    }

    public function removeOption(string $type, string $value): void
    {
        CrmOption::query()->where('type', $type)->where('value', $value)->update(['is_active' => false]);
    }
}
