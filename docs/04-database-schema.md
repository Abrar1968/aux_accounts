# 04 — Database Schema (MySQL / MariaDB, XAMPP)

Engine InnoDB, charset `utf8mb4_unicode_ci`. Money `DECIMAL(15,2)`, percentages `DECIMAL(7,4)`
(0.1500 = 15 %), business dates `DATE`, receipt timestamps `DATETIME` (Asia/Dhaka). Migrations:
`database/migrations/2026_11_01_*` (verified on MariaDB 10.11; compatible with XAMPP's MariaDB 10.4+).

## 1. Core / system

| Table | Key columns | Notes |
|---|---|---|
| `users` | role_id, name, email, password, avatar_path, is_active, last_login_at | Laravel default + extras |
| `roles` | name, label, permissions JSON | wildcard permissions (`*`, `accounting.*`) |
| `settings` | group, key (unique), value JSON `{v: …}`, editable | workbook Settings + CRM config |
| `periods` | month (unique, 1st of month), status historical/open/closed, closed_at, closed_by | period lock |
| `sequences` | key (unique), value | gap-free document numbers |
| `audit_logs` | user_id, action, auditable_type/id, before/after JSON, ip, reason | immutable |
| `attachments` | attachable morph, path, thumb_path, mime, size, width, height | GD processed |
| `automation_runs` | command, status, summary JSON, error, started/finished | Apps_Script status tab |
| `source_aliases` | alias, canonical, type, decision, confirmed | Source_Mapping |
| `notifications` | Laravel database notifications (uuid, type, notifiable, data JSON, read_at) | bell + centre |
| `notification_preferences` | user_id, type, in_app, email, push | per-user channels |
| `sessions`, `cache`, `jobs`, `failed_jobs`, `password_reset_tokens` | Laravel defaults | database drivers |

## 2. Accounting

| Table | Purpose | Important columns / constraints |
|---|---|---|
| `investors` | owners & partners | is_equity (Siam = false), capital_commitment |
| `investor_ownerships` | effective-dated % | unique(investor_id, effective_from) |
| `capital_transactions` | Capital_Ledger | txn_date, period, type, cash_impact, equity_impact, counterparty |
| `projects` | Projects + International_Projects | code unique (`PRJ-`, `IPRJ-`, `LEAD-`), kind project/international/lead, revenue_method, base_value, change_orders, dates, referrer, lead_id |
| `revenue_recognitions` | recognition ledger | period, project_id, opening/ending progress, contract_value, auto_revenue, override_amount, **recognized_amount**, siam_eligible, siam_share, recognition_month, entry_check, is_historical, auto_generated |
| `opening_receivables` | Opening_AR (`LEG-*`) | opening_amount |
| `marketing_services` | `MKT-*` | service_month, client, revenue |
| `collaborators`, `partnership_packages`, `partnership_assignments`, `partnership_revenues` | Partnerships | revenue unique(assignment_id, earned_month); method + share snapshot |
| `customer_payments` | Payments | code `PAY-YYMMDD-NNN`, paid_at, period, receivable_type (project/marketing/opening/partnership) + receivable_id, revenue_code, cash_received, processing_fee, method, account, request_id unique |
| `expense_categories` | managed category list | statement_line flag |
| `operating_expenses` | bills `EXP-NNN` | accrual_period, expense_date, **accrual_month**, category, subcategory, amount, legacy JSON (AA:AC snapshot) |
| `expense_payments` | permanent expense cash ledger | paid_on, operating_expense_id, amount, origin (`Migrated initial`, `Recorded from expense`, `Withheld fee`), request_id unique, posted_at/by, reverses_id |
| `employees` | staff master | slug = lower(trim(name)) used in payroll ids, base_salary, active |
| `payrolls` | Salary rows | code `PAYROLL-YYYYMM-name`, unique(employee_id, period), gross_salary, legacy snapshots |
| `salary_payments` | Salary_Payments | paid_on, payroll_id, amount, origin (`Historical payroll month`, `Recorded from salary`), request_id |
| `employee_advances` | `ADV-NNN` | txn_date, type Disbursement/Recovery, amount, related_salary_period, payroll_id |
| `referrals` | Referral_Tracker | project_id unique, referrer, memo_amount, paid_on, paid_amount |
| `fixed_assets` | Assets | cost, residual, life months, opening accumulated dep., disposal, cash paid/date |
| `liabilities` | Liabilities ledger | liability_code (LIA-DEF, LIA-AC, LIA-SIAM, LIA-SIAM-SHARE), increase, decrease, cash_impact, period |

Phase 4 tables (migration `2026_11_02_000500`):

| Table | Purpose | Columns |
|---|---|---|
| `bank_accounts` | bank / wallet / cash accounts | name, type, account_number, aliases JSON (matched against ledger account/method), opening_balance, opening_date, is_active |
| `bank_reconciliations` | statement vs book | bank_account_id, statement_date (unique per account), statement_balance, book_balance, difference, status |
| `budgets` | monthly budget per statement line | period, line_key (unique together), amount |
| `recurring_expenses` | bill templates | name, category, subcategory, amount, day_of_month, start_month, end_month, is_active |
| `operating_expenses.recurring_expense_id` | generated bill link | unique(recurring_expense_id, accrual_month) — idempotent generation |
| `caller_targets` | monthly CRM targets | owner, period (unique together), calls_target, leads_target, wins_target |

Still planned: `change_orders` (itemised), `employee_documents`.

## 3. CRM

| Table | Purpose | Columns |
|---|---|---|
| `lead_outcomes` | outcome → stage/score/follow-up days | 15 seeded rows |
| `crm_options` | services, sources, business types, industries | type + value unique |
| `leads` | Leads | code `YYYYMMDD-XXXXXXXX`, lead_date, phone, normalized_phone (indexed), business_type, industry, service, source, outcome, stage, score, owner/owner_id, quoted_price, followup_override, next_followup, meeting_at, meeting_details, last_activity_at, closed_date, deal_value, lost_reason, calls_made, notes, project_id |
| `lead_calls` | Call Log | call_uuid unique, lead_id, recorded_at, call_type, outcome, caller, notes, next_followup, status, count_change, entry_source |
| `crm_reports` | immutable report snapshots | type, period_start/end, status, hash (SHA-256), payload JSON; unique(type, start, end, hash) |

## 4. Relationships (ER summary)

```
investors 1─* investor_ownerships        investors 1─* capital_transactions
projects 1─* revenue_recognitions        projects 1─1 referrals        projects *─1 leads
projects/marketing_services/opening_receivables/partnership_revenues 1─* customer_payments (receivable_type+id)
operating_expenses 1─* expense_payments  employees 1─* payrolls 1─* salary_payments
employees 1─* employee_advances *─1 payrolls (recoveries)
collaborators 1─* partnership_assignments *─1 partnership_packages; assignments 1─* partnership_revenues
leads 1─* lead_calls                     users 1─* notifications, notification_preferences, audit_logs
any model 1─* attachments (morph)        any model 1─* audit_logs (type+id)
```

## 5. Indexing for statement speed
`revenue_recognitions(recognition_month)`, `customer_payments(paid_at)`, `operating_expenses(accrual_month, category)`,
`expense_payments(paid_on)`, `salary_payments(paid_on)`, `payrolls(period)`, `employee_advances(type, txn_date)`,
`liabilities(liability_code, period)`, `capital_transactions(period)`, `leads(stage, lead_date)`, `leads(next_followup)`.
