<?php

namespace Tests\Feature;

use App\Services\Accounting\FinancialStatementService;
use App\Support\Period;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Oracle test: the seeded real workbook data must reproduce Monthly_Statements Apr–Nov 2026
 * to the paisa (tests/Fixtures/golden_monthly_statements.json). Fix services, never the fixture.
 */
class GoldenStatementsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = DatabaseSeeder::class;

    public function test_every_statement_line_matches_the_workbook(): void
    {
        $golden = json_decode(file_get_contents(base_path('tests/Fixtures/golden_monthly_statements.json')), true);
        $svc = app(FinancialStatementService::class);
        $checked = 0;
        foreach ($golden as $month => $lines) {
            $actual = collect($svc->lines(Period::of($month)))->keyBy('label');
            foreach ($lines as $label => $expected) {
                $this->assertArrayHasKey($label, $actual->all(), "$month: missing line $label");
                $this->assertEqualsWithDelta($expected, $actual[$label]['amount'], 0.005, "$month: $label");
                $checked++;
            }
            $this->assertSame(0.0, (float) $actual['BALANCE CHECK']['amount'], "$month balance check");
        }
        $this->assertSame(400, $checked);
    }

    public function test_known_spot_checks(): void
    {
        $oct = app(FinancialStatementService::class)->forPeriod(Period::of('2026-10'));
        $this->assertEqualsWithDelta(300000, $oct['siam_liability'], 0.001);
        $this->assertEqualsWithDelta(38621, $oct['payables'], 0.001);
        $this->assertEqualsWithDelta(62160, $oct['bank_overdraft'], 0.001);
        $this->assertEqualsWithDelta(-1910700, $oct['opening_balance'], 0.001);
    }
}
