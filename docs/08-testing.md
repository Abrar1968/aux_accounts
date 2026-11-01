# 08 — Testing Strategy

Runner: PHPUnit 11 (`php artisan test`). Test DB: SQLite in-memory (fast, isolated); a CI job also
runs the suite on MariaDB to catch MySQL-specific SQL. Frontend: `npm run build` must pass (type/compile check).

## 1. Layers
| Layer | Location | What | Examples |
|---|---|---|---|
| Unit | `tests/Unit` | pure calculators & value objects | Period, Money, ReferralTierCalculator (all 8 tiers + boundaries 29,999/30,000…), DepreciationCalculator (cap at base, disposal, not-in-service), RecognitionCalculator (every entry-check branch, Siam window edges), PhoneNormalizer (BD local / 10-digit / +880 / international / invalid), LeadComputer (stage, score, follow-up, priority, closed date) |
| Service | `tests/Feature/Services` | services with DB | PaymentPostingService (saved, already_saved, conflict, overpayment, future date, >2 dp, withheld fee, salary remaining incl. recoveries, period locked), CustomerPaymentService, PayrollService, AdvanceService linking, RevenueRecognitionService auto rows idempotent, PartnershipService generation rules, CallLogService idempotency, count update/correction |
| Golden | `tests/Feature/GoldenStatementsTest.php` | seed real data → every line of every month Apr–Nov 2026 equals `tests/Fixtures/golden_monthly_statements.json` (±0.005) and balance check = 0 | 8 months × 50 lines = 400 assertions |
| Golden CRM | `tests/Feature/CrmSnapshotTest.php` | seeded CRM → 142 leads, 93 active, 0 won; per-lead stage/score match workbook; call totals match | |
| HTTP | `tests/Feature/Http` | Inertia responses (`assertInertia`), validation errors, permission 403, toast flash, closed-period redirect | |
| Notifications | `tests/Feature/Notifications` | `Notification::fake()`, channel selection by preference, schedule commands send expected notifications | |
| Automation | `tests/Feature/Commands` | `accounting:daily` idempotent (run twice → same rows), `crm:safety-check` report cursors, dedupe hash | |

## 2. Golden test outline
```php
public function test_statements_match_workbook(): void
{
    $this->seed(DatabaseSeeder::class);
    $golden = json_decode(file_get_contents(base_path('tests/Fixtures/golden_monthly_statements.json')), true);
    $svc = app(FinancialStatementService::class);
    foreach ($golden as $month => $lines) {
        $s = collect($svc->lines(Period::of($month)))->keyBy('label');
        foreach ($lines as $label => $expected) {
            $this->assertEqualsWithDelta($expected, $s[$label]['amount'], 0.005, "$month $label");
        }
    }
}
```

## 3. Rules
* Every bug fix starts with a failing test. Coverage target ≥ 85 % for `app/Services`.
* No test touches Pusher/SMTP (`BROADCAST_CONNECTION=null`, `MAIL_MAILER=array` in phpunit.xml).
* Time-dependent tests use `$this->travelTo('2026-11-01 10:00:00')` (Asia/Dhaka).
* CI (`.github/workflows/tests.yml`): PHP 8.2, `composer install`, `npm ci && npm run build`, `php artisan test`.

## 4. Manual QA checklist (per release)
Phone 360 px + desktop: login, dashboard, record payment (retry same request → no duplicate),
pay expense installment, close/reopen period, lead create with duplicate phone, save call, pipeline drag,
print statement to PDF, dark mode, realtime toast across two browsers.
