# 03 — Backend Architecture (Laravel 12 + Inertia, Service–Repository)

## 1. Request flow

```
Browser (Vue page via Inertia)
  → routes/web.php            (grouped per module, named routes, auth + permission middleware)
  → Middleware                 auth, verified, permission:*, HandleInertiaRequests (shared props)
  → FormRequest                validation + authorize() only
  → Controller (thin)          calls ONE service method
        GET  → Inertia::render('Module/Page', [...DTO/Resource arrays...])
        POST/PUT/DELETE → redirect()->back()/route() ->with('toast', [...])
  → Service                    business rules, DB::transaction, period lock, events, notifications
  → Repository (interface)     queries + persistence, aggregates (sumBetween/sumBefore)
  → Eloquent Model             relations, casts, scopes, Auditable trait — no business rules
```

Rules
1. Controllers contain no business `if`s and never call Eloquent directly.
2. Every calculation lives in a service; pure formula ports live in `Services/Accounting/Engine` and are
   unit-tested without a database.
3. Services throw `App\Exceptions\Domain\*`. `bootstrap/app.php` turns them into a **flash toast +
   redirect back with input** for Inertia requests, or JSON `{message, code}` for `fetch()` calls.
4. Inertia props are plain arrays produced by `App\Http\Resources\*` (money as numbers rounded to 2 dp,
   dates ISO `Y-m-d`), never raw models (avoids leaking columns).
5. Lazy / partial props (`Inertia::defer`, `only: [...]`) for heavy widgets (statement grid, analytics).
6. JSON endpoints exist only for UI helpers: global search, receivable picker, lead phone duplicate check.
   They live under `routes/web.php` → `/ajax/*`, session-authenticated, CSRF-protected.

## 2. Folder structure

```
app/
  Console/Commands/           AccountingDaily, CrmSafetyCheck, CrmDigest, NotifyDueItems, BackupDatabase
  Enums/                      RevenueMethod, ProjectKind, AdvanceType, PeriodStatus, LeadStage, CallType, NotificationLevel
  Events/                     PaymentPosted, PeriodClosed, LeadUpdated, AutomationFinished   (ShouldBroadcastNow → Pusher)
  Exceptions/Domain/          DomainException, PeriodLockedException, PostingRejectedException, IdempotencyConflictException
  Http/
    Controllers/
      Auth/                   Login, Logout, PasswordReset
      Accounting/             Dashboard, Statement, Project, Recognition, CustomerPayment, Receivable,
                              MarketingService, Partnership, Expense, ExpensePayment, Employee, Payroll,
                              SalaryPayment, Advance, Referral, Asset, Liability, Capital, Control, Period
      Crm/                    Today, Lead, Pipeline, CallLog, FollowUp, Meeting, Report, Analytics, CrmSetting
      System/                 Setting, User, Role, Automation, Notification, Attachment, Search
    Middleware/               HandleInertiaRequests, EnsurePermission
    Requests/<Module>/        one FormRequest per write action
    Resources/<Module>/       array shapers for Inertia props
  Models/                     one per table (+ Concerns/Auditable)
  Notifications/              FollowUpsDue, ReceivableOverdue, PayrollUnpaid, AutomationFailed, PeriodCloseReminder,
                              PaymentRecorded, DailyCrmDigest  (database + broadcast + mail channels)
  Policies/                   per-model policies delegating to permissions
  Providers/                  AppServiceProvider, RepositoryServiceProvider
  Repositories/Contracts/     *RepositoryInterface
  Repositories/Eloquent/      BaseRepository + *Repository
  Services/
    Accounting/               ProjectService, RevenueRecognitionService, ReceivableService, CustomerPaymentService,
                              ExpenseService, PaymentPostingService, PayrollService, AdvanceService, ReferralService,
                              AssetService, LiabilityService, CapitalService, PartnershipService,
                              FinancialStatementService, ControlsService, DashboardService, AccountingAutomationService
      Engine/                 ReferralTierCalculator, DepreciationCalculator, RecognitionCalculator
    Crm/                      LeadService, PhoneNormalizer, LeadComputer, CallLogService, DailyAllocationService,
                              CrmDashboardService, CrmReportService, AnalyticsService
    Media/                    ImageService (GD), AttachmentService
    Notifications/            NotificationService (fan-out + preferences)
    Shared/                   AuditService, SettingsService, SequenceService, PeriodService, SearchService
  Support/                    Money (integer paisa), Period (month value object)
resources/js/                 see 06-frontend-design-system.md
database/seeders/             DatabaseSeeder → Role, User, Settings, AccountingWorkbook, CrmWorkbook; data/**.json
tests/Unit, tests/Feature, tests/Fixtures/golden_monthly_statements.json
```

## 3. Key services (contracts)

| Service | Responsibility | Workbook origin |
|---|---|---|
| `FinancialStatementService::forPeriod(Period)` | 53 statement lines, grid, opening balance, depreciation | Monthly_Statements, month packs |
| `ReceivableService::balancesAt(cut)` | A/R + advance per revenue id, aging, picker | Receivables |
| `RevenueRecognitionService` | create/update rows, recompute derived columns, daily auto rows | Revenue_Recognition + `addProgressRows_` |
| `PaymentPostingService::post(kind, id, draft)` | idempotent expense/salary payments, overpayment guard | AuxtechPayments.gs |
| `CustomerPaymentService::record()` | receipts, fees, idempotency | Payments |
| `PayrollService` / `AdvanceService` / `ReferralService` | payroll ids, recoveries, referral memo | Salary, Employee_Advances, Referral_Tracker |
| `PartnershipService` | monthly rows for active assignments | Partnerships (never-installed script) |
| `ControlsService::run()` | every check, no auto-fix | Checks |
| `AccountingAutomationService::runDaily()` | periods, partnerships, recognition, controls | `dailyAuxtechAutomation` |
| `LeadComputer` / `LeadService` / `CallLogService` | lead derivations, counts, calls | LeadCRM.gs |
| `CrmReportService` | daily/monthly/yearly snapshots with SHA-256 dedupe | `exportReport_` |
| `NotificationService` | event → recipients → channels by preference | new |
| `ImageService` | GD resize/thumbnail/re-encode | new |

Status of the head-start code already in the repo: migrations, models, repositories, `Support/*`,
`Services/Shared/*`, engine calculators and the main accounting services are drafted and must be
verified/finished by the Phase-1 backend prompt ([10-prompts.md](10-prompts.md)).

## 4. Cross-cutting

* **Database:** MySQL 8 / MariaDB 10.4+ (XAMPP ships MariaDB 10.4). `utf8mb4_unicode_ci`, InnoDB,
  `DECIMAL(15,2)` money, `DATE` business dates, `DATETIME` for receipt timestamps (stored in Asia/Dhaka
  because `APP_TIMEZONE=Asia/Dhaka`). Avoid MySQL-8-only SQL (no `JSON_TABLE`, no window functions in
  hot paths) so MariaDB 10.4 works.
* **Audit:** `Auditable` trait → `audit_logs` (before/after JSON, user, IP, reason).
* **Period lock:** `PeriodService::assertOpen(...dates)` before every write.
* **IDs:** `SequenceService` (row-locked counters) reproducing `PAY-YYMMDD-NNN`, `EXP-NNN`, `PRJ-YYMM-NNN`…
* **Broadcasting:** `BROADCAST_CONNECTION=pusher`, events `ShouldBroadcastNow`, channel auth in
  `routes/channels.php`.
* **Queue:** `database`; scheduler drains it every minute (mail notifications).
* **Images:** GD → EXIF orientation, max 1600 px, 320 px thumb, JPEG q82/WebP q80, metadata stripped,
  10 MB limit, private disk, signed URLs.

## 5. Scheduler (`routes/console.php`, Asia/Dhaka)

| When | Command | Purpose |
|---|---|---|
| every minute | `queue:work --stop-when-empty --max-time=50` | send queued mail |
| every 5 min | `crm:safety-check` | report cursors, call retries |
| 02:10 daily | `accounting:daily` | periods, partnerships, recognition, controls |
| 08:30 daily | `notify:due` | follow-ups due, overdue A/R, unpaid payroll (in-app + mail) |
| 08:35 daily | `crm:digest` | per-caller follow-up email |
| 25th 10:00 | `notify:period-close` | month-end close checklist |
| 03:00 daily | `backup:database` | mysqldump to `storage/app/backups` (30-day retention) |

Local (XAMPP): run `php artisan schedule:work` in a terminal. Hosting: one cron line (see 11).
