<?php

namespace Tests\Unit;

use App\Support\Money;
use App\Support\Period;
use Tests\TestCase;

class PeriodMoneyTest extends TestCase
{
    public function test_period_normalises_to_first_of_month_and_cut(): void
    {
        $p = Period::of('2026-10-31 23:59:00');
        $this->assertSame('2026-10-01', $p->date());
        $this->assertSame('2026-11-01', $p->cut());
        $this->assertSame('2026-10-31', $p->end());
        $this->assertSame('Oct-2026', $p->label());
        $this->assertSame('2027-01', Period::of('2026-12')->next()->key());
        $this->assertCount(8, Period::range(Period::of('2026-04'), Period::of('2026-11')));
    }

    public function test_money_rounds_through_paisa(): void
    {
        $this->assertSame(0.3, Money::sum([0.1, 0.2]));
        $this->assertSame(1234.57, Money::round(1234.565));
        $this->assertTrue(Money::hasAtMostTwoDecimals(10.25));
        $this->assertFalse(Money::hasAtMostTwoDecimals(10.255));
        $this->assertSame('-৳1,500.00', Money::format(-1500));
    }
}
