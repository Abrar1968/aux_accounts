<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Services\Crm\LeadComputer;
use App\Services\Crm\PhoneNormalizer;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class CrmComputersTest extends TestCase
{
    private const OUTCOMES = [
        'New' => ['stage' => 'New', 'score' => 10, 'followup_days' => 0],
        'No Response' => ['stage' => 'Contacted', 'score' => 20, 'followup_days' => 1],
        'Interested' => ['stage' => 'Interested', 'score' => 60, 'followup_days' => 1],
        'Develop Later' => ['stage' => 'Contacted', 'score' => 30, 'followup_days' => 30],
        'Meeting Set' => ['stage' => 'Meeting', 'score' => 75, 'followup_days' => 1],
        'Won' => ['stage' => 'Won', 'score' => 100, 'followup_days' => 0],
        'Lost' => ['stage' => 'Lost', 'score' => 0, 'followup_days' => 0],
        'Office Visit — Scheduled' => ['stage' => 'Meeting', 'score' => 75, 'followup_days' => 1],
    ];

    public function test_phone_normalisation(): void
    {
        $p = new PhoneNormalizer;
        $this->assertSame('8801700000000', $p->normalize('01700000000'));
        $this->assertSame('8801700000000', $p->normalize('1700000000'));
        $this->assertSame('8801700000000', $p->normalize('+8801700000000'));
        $this->assertSame('8801703914789', $p->normalize('0 1703-914789'));
        $this->assertSame('12025550123', $p->normalize('+1 (202) 555-0123'));
        $this->assertSame('447700900123', $p->normalize('+44 7700 900123'));
        $this->assertNull($p->normalize('12345'));
        $this->assertNull($p->normalize(85586892617));
        $this->assertNull($p->normalize('01200000000'), 'BD operator digit must be 3-9');
    }

    private function lead(array $attrs): Lead
    {
        return (new LeadComputer(new PhoneNormalizer))->apply(new Lead(array_merge(['lead_date' => '2026-10-07', 'phone' => '01700000000'], $attrs)), self::OUTCOMES);
    }

    public function test_follow_up_rules(): void
    {
        $this->assertSame('2026-10-09', $this->lead(['outcome' => 'No Response', 'last_activity_at' => '2026-10-08 01:26:20'])->next_followup->toDateString());
        $this->assertSame('2026-11-07', $this->lead(['outcome' => 'Develop Later', 'last_activity_at' => '2026-10-08 12:00'])->next_followup->toDateString());
        $this->assertSame('2026-10-07', $this->lead(['outcome' => 'New'])->next_followup->toDateString(), 'no activity: lead date + 0');
        $this->assertSame('2026-10-20', $this->lead(['outcome' => 'Interested', 'followup_override' => '2026-10-20 10:00'])->next_followup->toDateString());
        $this->assertSame('2026-10-15', $this->lead(['outcome' => 'Meeting Set', 'meeting_at' => '2026-10-15 15:00', 'last_activity_at' => '2026-10-08'])->next_followup->toDateString());
        $this->assertSame('2026-10-09', $this->lead(['outcome' => 'Meeting Set', 'last_activity_at' => '2026-10-08'])->next_followup->toDateString());
        $this->assertNull($this->lead(['outcome' => 'Won'])->next_followup);
    }

    public function test_priority_days_open_and_checks(): void
    {
        $c = new LeadComputer(new PhoneNormalizer);
        $today = CarbonImmutable::parse('2026-11-02');
        $this->assertSame('OVERDUE', $c->priority($this->lead(['outcome' => 'No Response', 'last_activity_at' => '2026-10-08']), $today));
        $this->assertSame('TODAY', $c->priority($this->lead(['outcome' => 'No Response', 'last_activity_at' => '2026-11-01']), $today));
        $this->assertSame('HIGH', $c->priority($this->lead(['outcome' => 'Interested', 'last_activity_at' => '2026-11-03']), $today));
        $this->assertSame('NORMAL', $c->priority($this->lead(['outcome' => 'Develop Later', 'last_activity_at' => '2026-11-01']), $today));
        $this->assertSame('CLOSED', $c->priority($this->lead(['outcome' => 'Lost']), $today));
        $this->assertSame(26, $c->daysOpen($this->lead(['outcome' => 'New']), $today));
        $this->assertSame(['MISSING CLOSED DATE', 'ADD LOST REASON'], $c->dataChecks($this->lead(['outcome' => 'Lost']), false));
        $this->assertSame(['CHECK PHONE', 'DUPLICATE PHONE', 'ADD OFFICE VISIT DATE/TIME'], $c->dataChecks($this->lead(['outcome' => 'Office Visit — Scheduled', 'phone' => '123']), true));
        $this->assertSame(['MISSING CLOSED DATE', 'ADD DEAL VALUE'], $c->dataChecks($this->lead(['outcome' => 'Won']), false));
    }
}
