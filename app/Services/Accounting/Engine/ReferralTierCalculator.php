<?php

namespace App\Services\Accounting\Engine;

use App\Support\Money;

/**
 * Projects!N — referral memo policy:
 * <30k=500; <60k=600; <100k=1,000; <250k=1.5%; <500k=2.5%; <750k=5%; <1m=7.5%; >=1m=10%.
 */
class ReferralTierCalculator
{
    /** @param list<array{below:?int,fixed?:float,rate?:float}> $tiers */
    public function __construct(private readonly array $tiers)
    {
    }

    public function memo(float $totalContractValue): float
    {
        foreach ($this->tiers as $tier) {
            if ($tier['below'] === null || $totalContractValue < $tier['below']) {
                return isset($tier['fixed'])
                    ? Money::round($tier['fixed'])
                    : Money::round($totalContractValue * $tier['rate']);
            }
        }

        return 0.0;
    }
}
