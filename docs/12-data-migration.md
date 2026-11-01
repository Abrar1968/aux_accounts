# 12 — Data Migration (Workbooks → Seeders)

All real rows were exported with a read-only script into `database/seeders/data/**` (values only; formulas
are re-implemented in services). Blank template rows were dropped. Golden statement values are in
`tests/Fixtures/golden_monthly_statements.json`.

## 1. Files

| JSON | Rows | Target table(s) | Mapping notes |
|---|---|---|---|
| accounting/settings.json | 19 | settings, periods | policy rows → keys of `SettingsService::DEFAULTS`; reporting months → periods (Apr–Sep `historical`, Oct+ `open`) |
| accounting/source_mapping.json | 13 | source_aliases | — |
| accounting/investors.json | 6 (+1 note row, skip) | investors, investor_ownerships | ownership Apr–Aug effective 2026-04-01, "From Sep-1" effective 2026-09-01; Siam `is_equity=false` |
| accounting/capital_ledger.json | 8 | capital_transactions | Investor name → id |
| accounting/opening_ar.json | 5 | opening_receivables | Revenue ID → code |
| accounting/projects.json | 18 | projects | Type `Lead` → kind lead; Project ID → code; Source Month/Start/Est./Actual dates |
| accounting/international_projects.json | 0 | projects (kind international) | empty today |
| accounting/revenue_recognition.json | 17 | revenue_recognitions | all `is_historical=true`; Contract/Change Value → contract_value; Override Revenue → override_amount; Method → method_label |
| accounting/payments.json | 43 | customer_payments | Revenue ID prefix → receivable_type (`PRJ`/`IPRJ`/`LEAD` project, `MKT` marketing, `LEG` opening, `PART` partnership); Account/Notes → account; blank fee → 0 |
| accounting/marketing_services.json | 2 | marketing_services | — |
| accounting/operating_expenses.json | 109 | operating_expenses, expense_categories | accrual_month = month(Accrual Period ?? Expense Date); Legacy Initial * → legacy JSON |
| accounting/expense_payments.json | 107 | expense_payments | Entry Origin → origin (95 *Migrated initial*, 9 *Recorded from expense*, 3 *Withheld fee*); datetime dates → date |
| accounting/salary.json | 63 | employees, payrolls | employee names create employees; Payroll ID → code |
| accounting/salary_payments.json | 71 | salary_payments | 56 *Historical payroll month* (zero allowed) + 15 recorded |
| accounting/employee_advances.json | 50 | employee_advances | Recovery Payroll ID → payroll_id |
| accounting/referrals.json | 16 | referrals | Project ID → project_id; Paid Month → paid_on |
| accounting/assets.json | 1 | fixed_assets | AC: cost 132,000, life 60, opening accum. 19,800 |
| accounting/liabilities.json | 10 | liabilities | Decrease / Payment → decrease |
| accounting/collaborators.json | 4 | collaborators | — |
| accounting/partnership_packages.json | 5 | partnership_packages + partnership_assignments | same row holds package (A:E) and assignment (G:R) |
| accounting/opening_balance.json | 18 | settings `opening.*` | only USER INPUT rows (all 0 today) |
| crm/leads.json | 172 (142 with phone) | leads | skip rows without phone (unused daily slots); keep Lead ID, recompute derived fields with LeadComputer and compare |
| crm/call_logs.json | 72 | lead_calls | 12 Follow-up, 59 Count update, 1 Count correction |
| crm/automation_log.json | 196 | automation_runs (history) | action/result/details |

Settings → CRM lists (outcomes, services, sources, owners, business types, industries) are seeded from
the CRM Settings tab values documented in 01 §3.

## 2. Sequence bumps after import
`EXP`→109, `PAY`→43, `ADV`→50, `REF`→17, `PRJ`→18, `RR`→18, `SPAY`/`EPAY` use UUIDs.

## 3. Verification
1. `php artisan migrate:fresh --seed`
2. `php artisan test --filter=GoldenStatementsTest` → 0 differences Apr–Nov 2026.
3. Spot checks: Shaown legacy A/R 56,200; Siam liability 300,000; Oct payables 38,621; Oct overdraft 62,160.
4. Known data issues (do not "fix" in the seeder), all surfaced by the Controls page:
   - Oct-2026 payroll rows missing for **Rajib Vai** and **Graphics Designer** (the workbook only reported Rajib).
   - 16 historical projects lack delivery dates (recognised provisionally in the source month).
   - Opening balance is provisional (−1,910,700).
   - Partnership assignment ASM-0003 (WTBL Maintenance) is *Active* from 2026-08-01 although its note says
     "example until agreement confirmed"; the daily automation creates Aug→current monthly rows with a blank
     earned amount (no revenue until an amount is entered). Set it to Draft if the agreement is not signed.
   - Lead `20261008-…` phone stored as the number 85586892617 (invalid → CHECK PHONE), as in the workbook.
   - Partnership_Settings uses the header "Status" twice (package C and assignment L); the seeder maps them explicitly.

## 4. Privacy
Seed files contain real customer names, phone numbers and staff salaries. Keep the repository private.
