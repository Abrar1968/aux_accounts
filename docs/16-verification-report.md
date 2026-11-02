# 16 — Pre-deployment Verification Report (2026-11-02)

This is a full audit of data, calculations, backend, frontend and deployment readiness. It was run on branch
`claude/eager-bardeen-qu7nti`.

## 1. Data completeness: source xlsx vs database
An independent script re-read both original workbooks and compared every row against the seeded DB.
It found **27 datasets with 0 missing and 0 extra rows**:

| Dataset | Rows |
|---|---|
| Projects | 18 |
| Revenue recognition | 17 |
| Payments | 43 |
| Marketing | 2 |
| Opening A/R | 5 |
| Expenses | 109 |
| Expense payments | 107 |
| Salary | 63 |
| Salary payments | 71 |
| Advances | 50 |
| Referrals | 16 |
| Assets | 1 |
| Liabilities | 10 |
| Capital | 8 |
| Investors | 6 |
| Collaborators | 4 |
| Packages | 5 |
| Assignments | 5 |
| Source mapping | 13 |
| CRM leads | 142 |
| Call log | 72 |
| Outcomes | 15 |
| CRM lists | — |

The 30 CRM daily slots without a phone number are unused template rows, so they were intentionally not imported.
A negative control (deliberately corrupting one value) confirmed the comparator detects differences.

## 2. Calculations vs workbook formulas
**1,684 computed values were compared, with 0 real differences.** Coverage:
- monthly statements: 400 lines, Apr–Nov 2026, balance check 0 every month;
- receivables per Revenue ID per month;
- asset schedule;
- expense status and payables;
- salary reconstruction;
- marketing, referrals (tiers), liabilities (per id), opening A/R, investors and Siam share;
- opening balance −1,910,700;
- advances;
- the Checks sheet;
- CRM dashboard (incl. daily trend) and CRM analytics.

The only "differences" came from a workbook registry row literally named "Total" (a zero-balance artifact, not a totals row).

## 3. Automated tests
- SQLite (in-memory): **66 passed / 2,533 assertions**.
- MariaDB 10.11: **66 passed / 2,533 assertions**.
- New in this audit:
  - `RouteSecurityTest`: every route has auth + permission, and all 5 roles GET every page with no 5xx.
  - `RegressionFormsTest`: one test per bug fixed below.

## 4. End-to-end browser run (Playwright, real MariaDB)
**32/32 flows pass, with 0 JS errors and 0 failed requests.** The flows:
- project → delivery → auto recognition;
- receipt with fee;
- bill + instalment + overpayment block;
- GD image upload;
- recurring bill generation;
- employee → payroll draft → pay → payslip;
- advance; referral pay;
- asset + financing;
- liability; bank reconcile; budgets variance; partnership earned amount;
- period close/reopen;
- statements still balanced after all postings;
- lead with live duplicate check → call → set total → Won → convert to project;
- pipeline keyboard move; report snapshot; caller targets; CSV import preview/commit;
- settings, users, roles, notifications, profile;
- Ctrl K search, logout, forgot password.

**Page sweep:** 153 page-views at 360 / 768 / 1440 px, with 0 JS errors and 0 horizontal overflow (light + dark).

## 5. Bugs found and fixed in this audit
| Bug | Fix |
|---|---|
| 500 on asset create with blank residual/opening dep./cash | `AssetService` defaults blank amounts to 0 |
| 500 on liability entry with blank increase/decrease | `LiabilityService` defaults to 0 |
| 500 on user create (`email_verified_at` not fillable) | added to `User::$fillable` |
| Receipt "now" rejected as future (date-only default vs datetime rule) | form defaults to local datetime; rule `before:tomorrow` |
| Payslip/payroll showed "[object Object]" for employee | Resources merge computed keys over relations |
| Textarea/Combobox labels not linked to inputs (a11y) | generated ids + `for` |
| Asset with no cash paid silently unbalanced the balance sheet (same as the workbook) | warning toast + form hint: post the unpaid part as a liability (e.g. LIA-AC) |
| Phase 4 migration `down()` failed on MySQL (dropped index used by FK) | drop FK → unique → column; full `migrate:reset` verified on MariaDB + SQLite |

## 6. Deployment checks
| Check | Result |
|---|---|
| `composer audit` | no advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `php artisan optimize` (config/route/view/event cache) | OK |
| `schedule:list` | 7 jobs (queue drain, safety check, daily, backup, due reminders, CRM digest, period-close) |
| `migrate:reset` + `migrate` | OK on MariaDB and SQLite |
| PHP 8.3-only syntax scan / `check-platform-reqs` | none / OK (platform pin 8.2.12) |
| Secrets in git | none (`.env` ignored; `.env.example` has blank `PUSHER_APP_SECRET`) |

## 7. Must do on the host before go-live
1. `.env`:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, fresh `APP_KEY`;
   - `SESSION_SECURE_COOKIE=true`;
   - real `MAIL_*` SMTP credentials;
   - `PUSHER_APP_SECRET`;
   - DB credentials.
2. Run:
   - `composer install --no-dev -o`
   - `npm ci && npm run build`
   - `php artisan migrate --seed --force` (first deploy only)
   - `php artisan storage:link`
   - `php artisan optimize`
3. Add the cron line: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`.
4. Set `ADMIN_PASSWORD` in `.env` before seeding, or change all seeded user passwords (default `ChangeMe!2026`).
5. Repository must stay **private** (real customer and salary data in seeders).

## 8. Data items for the business to resolve (not code bugs)
These are faithful to the workbook and flagged on the Controls page:
- Oct-2026 payroll missing for Rajib Vai and Graphics Designer.
- 16 historical projects lack delivery dates.
- The opening balance (−1,910,700) is provisional.
- WTBL assignment ASM-0003 is *Active*; set it to Draft if unsigned.
- 207 historical cash movements have no bank/wallet name (৳4,05,819 "Unassigned" at Oct end); add aliases on Bank & cash.
- The cash forecast goes negative (≈ −৳7.0 L by end of Jan) on current payables/receivables.

## 9. Not yet done (Phase 2 hardening / Phase 5)
- Lighthouse score.
- Manual phone QA and realtime-toast test with live Pusher across two browsers.
- Real SMTP delivery test.
- The 85 % service-coverage target has not been measured (no coverage driver in the container).
- A CI workflow for the MariaDB suite.
