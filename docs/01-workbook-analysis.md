# 01 — Workbook Analysis (Source-of-Truth Reverse Engineering)

This document records how the two Google-Sheets workbooks and three Apps Script
modules work today. The Laravel system must reproduce **every** rule here. The
numbers in §4 are the *golden values* the automated test-suite asserts against
(`tests/Fixtures/golden_monthly_statements.json`).

Sources analysed

| File | Tabs | Real data rows |
|---|---|---|
| `Auxtech_Accrual_Accounting_Master_2026.xlsx` | 41 | ~600 ledger rows (Apr–Nov 2026) |
| `Automated_daily_lead_sheet.xlsx` (Lead CRM) | 15 | 142 leads, 72 call/count entries, 196 automation log rows |
| `Code.gs` (monthly automation v2.0.0) | — | month creation, receivable sync, progress rows |
| `AuxtechPayments.gs` (payments v2.0.0) | — | idempotent expense/salary payment posting |
| `LeadCRM.gs` (v1.2.0) | — | daily slots, call log, counts, PDF reports |

---

## 1. Accounting workbook — business model

* **Basis: accrual.** Revenue when earned, expenses when incurred, cash is a separate
  dimension. Every transaction therefore has *two* dates: an **accrual/earned period**
  and an **actual cash date**.
* **Currency:** BDT (৳), two decimals.
* **Timezone:** Asia/Dhaka. Model start 2026‑04‑01, opening balance date 2026‑03‑31.
* **Reporting period = calendar month.** A month is identified by its first day.
  All "month of X" fields are `EOMONTH(X,-1)+1`.

### 1.1 Master/input ledgers

| Tab | Purpose | Key inputs | Auto fields |
|---|---|---|---|
| Settings | Policies: AC life 60 m, residual 0, Siam start 2026‑11‑01, Siam months 24, Siam share 15 %, referral policy *When Paid*; reporting-month list | editable policies | — |
| Source_Mapping | Alias → canonical record decisions (e.g. `wtb / wtb_singapore → PRJ-APR-WTB-SG`) | alias, canonical, decision | — |
| Investors | Owners, ownership % (Apr–Aug / from Sep‑1), commitments | ownership | paid-in = Σ capital equity impact |
| Capital_Ledger | Capital contributions, transfers | date, investor, type, cash impact, equity impact | period |
| Opening_AR | Pre-April dues (LEG‑*) | opening amount | current balance = max(0, opening − Σ gross settlements) |
| Projects / International_Projects | Project registers (domestic `PRJ-YYMM-NNN`, international `IPRJ-YYMM-NNN`) | name, start, est./actual delivery, method, base value, change orders, referrer | total value, referral memo, progress, payment status, cash, fees, gross settlement, revenue recognised, A/R, advance, last payment, fully-paid date, collection %, work status |
| Revenue_Recognition | Unified recognition ledger. Rows 4‑21 historical, 22+ shared queue | period, project, ending cum. progress (over-time), override | opening progress, contract value, auto revenue, recognised revenue, project type, Siam eligible revenue & share, recognition month, entry check |
| Payments | Customer receipts (only real cash) | date, source name, cash received, processing fee, method, account | payment id `PAY-YYMMDD-NNN`, period, revenue id lookup, gross settlement = cash + fee, revenue type |
| Marketing_Services | Recurring service revenue `MKT-YYMM-NNN` | service month, client, revenue earned | cash, last payment date, A/R, advance, status |
| Operating_Expenses | Bills `EXP-NNN` | accrual period, expense date, category, subcategory, description, amount incurred, payment draft | payable, total settled, status, entry check |
| Expense_Payments | Permanent expense cash ledger (`EPAY-…`) | payment date, expense id, amount, method, account, note, origin, request id | description, accrual month, payment month, entry check, cash out (0 for *Withheld fee*) |
| Employee_Advances | Salary advances `ADV-NNN` | date, employee, Disbursement/Recovery, amount, related salary period | recovery payroll month, recovery payroll id, entry check |
| Referral_Tracker | One row per project with referrer | paid date, paid amount | project/month/referrer/memo, expense month, status |
| Salary | Payroll, one row per employee/month (`PAYROLL-YYYYMM-employee`) | period, employee, gross | referral paid, advance recovery, unpaid, cash paid, status |
| Salary_Payments | Permanent salary cash ledger | date, payroll id, amount, method, account | employee, payroll month, payment month |
| Assets | Fixed-asset register `AST-NNN` | dates, cost, residual, life, opening accum. dep., disposal, cash paid | monthly depreciation, status |
| Liabilities | Liability ledger: LIA-DEF (prior dues), LIA-AC (AC financing), LIA-SIAM (deferred contract), LIA-SIAM-SHARE (share payments) | date, id, type, increase, decrease, cash impact | period, running balance |
| Siam_Contract | ৳500,000 non-refundable funding, revenue share 15 % of *international* recognised revenue for 24 months from 2026‑11‑01 | facts | eligible revenue, accrued share |
| Collaborators / Partnership_Settings / Partnerships | Partnership module: collaborators, packages, assignments, monthly earned rows (Direct Amount or Share of Sales) | earned amount / gross sales, receipts | recognised revenue, A/R, advance, status |
| Opening_Balance | Provisional 31‑Mar position | other assets/deposits | historical balancing amount (−1,910,700) |

### 1.2 Reports (calculation-only tabs)

Receivables (per Revenue ID, month-end A/R and Advance), Asset_Schedule (monthly depreciation
and NBV), Monthly_Statements (Income statement + Cash reconciliation + Balance sheet per month),
month packs `Apr_2026 … Nov_2026`, Checks, Dashboard.

---

## 2. Calculation rules (exact)

Notation: `m` = month start, `cut` = first day of the next month.

### 2.1 Projects
* `total_value = base_value + change_orders`
* **Referral memo** (policy tiers on total value): `<30k → 500; <60k → 600; <100k → 1,000;
  <250k → 1.5 %; <500k → 2.5 %; <750k → 5 %; <1m → 7.5 %; ≥1m → 10 %`.
* `cash_collected = Σ payments.cash`, `fees = Σ payments.fee`, `gross_settlement = Σ (cash+fee)`.
* `revenue_recognised = Σ revenue_recognitions.recognised` if method = Over Time or any recognition row
  exists; else `total_value` if delivered (actual delivery date set); else 0.
* `A/R = max(0, recognised − gross)`, `advance = max(0, gross − recognised)`.
* Payment status: Lead → *Not applicable*; no value → *Value needed*; gross>total → *Overpaid*;
  = → *Paid*; >0 → *Partially Paid*; else *Unpaid*.
* Work status: Lead → *Lead / Unconverted*; delivered → *Delivered*; started → *In progress*; else
  *Delivery not dated*.
* Progress %: over-time → max ending progress of OK recognition rows; else 1 if delivered, 0 otherwise.

### 2.2 Revenue recognition
* `opening_progress = max(ending_progress)` of earlier rows of the same project (period < m).
* `auto_revenue = round((ending − opening) × contract_value, 2)` only for Over-Time rows with OK check.
* `recognised = override if present else auto` (0 if entry check ≠ OK).
* Entry check order: project required → date required → unknown project → duplicate project/month →
  contract value required → invalid override → *Manual override required* (non over-time without
  override) → ending progress required → invalid progress (`<0, >1, < opening`) → OK.
* `siam_eligible = recognised` when project type is **International Project** and recognition month in
  `[siam_start, siam_start + 24 months)`; `siam_share = round(eligible × 15 %, 2)`.
* Automation (daily): **Point-in-time** projects with an actual delivery date ≤ current month and
  no recognition yet get one automatic row (period = delivery date, override = contract value).
  **Over-time** projects started ≤ current month and not delivered before the month get one draft row
  per month (progress entered by the user; no revenue until progress is valid).
  Leads never create revenue.

### 2.3 Receivables (per Revenue ID)
`balance(cut) = opening_ar + Σ recognised (rec. month < cut) + Σ marketing revenue (service month < cut)
+ Σ partnership revenue (earned month < cut) − Σ gross settlements (payment date < cut)`;
`A/R = max(0, balance)`, `advance = max(0, −balance)`.

### 2.4 Expenses & payments
* `accrual_month` = month of accrual period, else month of expense date.
* `settled = Σ expense_payments.amount (date set)`, `payable = max(0, cost − settled)`,
  status Unpaid / Partially Paid / Paid (±0.01).
* Payment cash out = amount, except origin *Withheld fee* → 0 (fee already withheld by the bank on a
  customer receipt).
* Processing fees on **customer payments** are expensed automatically in *Bank & Transfer Fees* in the
  payment month; Operating_Expenses rows with subcategory *Processing fee* are excluded from the P&L
  to avoid double counting.
* **Posting rules (AuxtechPayments.gs → `PaymentPostingService`)**: positive amount, ≤ 2 decimals,
  date between 2000‑2100 and not in the future, idempotent *request id* (same request with same details
  → `already_saved`; different details → error), source bill must exist exactly once and be valid,
  withheld processing fees cannot be paid, overpayment beyond remaining balance rejected (salary
  remaining also subtracts advance recoveries), capacity guard, document lock.

### 2.5 Payroll
* Payroll id `PAYROLL-YYYYMM-lower(trim(employee))`; one per employee/month.
* `referral_paid` (info) = referral payments to the employee in the payroll month.
* `advance_recovery = Σ recoveries linked to the payroll id`.
* `cash_settled = Σ salary payments`. `unpaid = max(0, gross − recovery − cash_settled)`.
* `cash_paid (display) = cash_settled + referral_paid`.
* Status: entry check ≠ OK → that text; unpaid ≤ 0.01 → Settled; recovery+cash > 0 → Partly settled;
  else Unpaid.

### 2.6 Employee advances
* Recovery payroll month = related salary period month, else transaction month;
  payroll id built like 2.5; recovery must link to exactly one payroll.
* Advance receivable at `cut` = Σ disbursements (date < cut) − Σ recoveries (date < cut).
* Recoveries are **non-cash** (reduce salary cash, not cash).

### 2.7 Referrals
* Auto-created per project with a referrer; memo from 2.1. Expense recognised **only when paid**
  (paid date month). Status Unpaid / Payment date required / Partially Paid / Overpaid / Paid.

### 2.8 Fixed assets
* `monthly_dep = (cost − residual) / life`.
* For each model month: dep = 0 if not acquired/in service before `cut` or disposed before `m`;
  else `max(0, min(monthly_dep, cost − residual − opening_accum − Σ prior model-month dep))`.
* NBV = `max(residual, cost − opening_accum − Σ dep through m)` (0 if not acquired before `cut`).

### 2.9 Liabilities
* Balance per liability id at `cut` = max(0, Σ increase − Σ decrease) for period < cut.
* LIA-SIAM-SHARE balance = Σ accrued Siam share − Σ share payments.

### 2.10 Monthly statement (53 lines)

Income statement
1. Project revenue = Σ recognised (rec. month = m)
2. Marketing service revenue = Σ marketing revenue (service month = m)
3. Total revenue = 1 + 2 + partnership revenue (earned month = m)
4. Salary expense = Σ payroll gross (payroll month = m)
5. Referral commissions paid = Σ referral paid (paid date in m)
6. Category lines (accrual month = m): Rent, Marketing & Advertising, Software & Hosting, Office & Admin,
   Legal & License, **Bank & Transfer Fees** (category excluding subcategory *Processing fee* + customer
   payment fees dated in m), Repairs & Equipment, Bonuses,
   **Other operating** = Σ all non-processing-fee expenses + payment fees − named lines.
7. Depreciation, Siam revenue share (rec. month = m), Total expenses, Net income.

Cash reconciliation: beginning = previous ending (April = 0) + customer cash receipts (by date) +
owner capital cash in (cash impact > 0) + liability cash in (cash impact > 0) − salary payments −
referral payments − expense cash out − advances disbursed − liability cash paid − direct asset cash.

Balance sheet: Cash = max(0, ending); A/R = Σ receivables; employee advance receivable; net fixed
assets; other assets (opening other + prepaid expenses + prepaid salary); prior company dues;
AC financing; Siam contract liability; customer advances; bank overdraft = max(0, −ending);
payables (expense + salary − recoveries + Siam share payable); paid-in capital (equity impact,
period < cut); provisional opening historical balance; cumulative net income; check =
assets − (liabilities + equity) **must be 0**.

### 2.11 Opening balance
Known assets (opening A/R 111,200 + AC NBV 112,200 + other inputs) − known liabilities (151,500 + 33,000)
− pre-April paid-in capital (1,949,600) = **−1,910,700** provisional historical balance.

### 2.12 Checks (controls)
Balance check per month; salary reconstruction difference; Siam share before contract start = 0;
point-in-time recognition integrity; referral sync; expense / salary / payment / advance / recognition
input warnings; project revenue ↔ recognition ledger reconciliation; payments without revenue id;
duplicate IDs (projects, payroll, payments, request ids); false *Paid* statuses; referral payments
without date; uncategorised expenses; historical projects awaiting delivery evidence (16);
asset disposals; provisional opening balance; migration difference; unsaved drafts; automation health;
partnership controls.

---

## 3. Lead CRM workbook

* **Launch 2026‑10‑07**, Asia/Dhaka day boundary, default owner *Abrar*, default website type
  *E‑commerce / Online Store*, 30 daily lead slots (1–500).
* **Outcome map** (Outcome → Stage, Score, default follow-up days): New→New 10/0, No Response→Contacted 20/1,
  Phone Off→Contacted 15/1, Call Later→Contacted 35/1, Interested→Interested 60/1, Meeting Set→Meeting 75/1,
  Demo/Proposal Sent→Proposal 85/2, Negotiation→Negotiation 90/1, Won→Won 100/0, Lost→Lost 0/0,
  Not Qualified→Lost 0/0, Develop Later→Contacted 30/30, Office Visit — Time Pending→Interested 65/1,
  Office Visit — Scheduled→Meeting 75/1, No Outcome→New 0/0.
* Lists: services (10), sources (10), owners (Abrar, Rizwan, Unassigned), business types (22),
  industries (68).
* **Lead fields**: date, id (`YYYYMMDD-XXXXXXXX`), phone, business type, industry, service, source,
  outcome, calls made, stage*, owner, quoted price, follow-up override, next follow-up*, priority*,
  score*, notes, WhatsApp link*, meeting date/time, meeting details, day*, days open*, duplicate*,
  last activity*, closed date*, deal value, lost reason, normalized phone*, data check*, total calls*,
  detailed follow-ups*, last call*, latest logged outcome* (*auto).
* **Phone normalisation**: strip spaces/()/-; `+CC…` (8–15 digits) → digits; `01[3-9]XXXXXXXX` →
  `88`+; `1[3-9]XXXXXXXX` → `880`+; `8801[3-9]…` kept; otherwise invalid → *CHECK PHONE*.
* **Next follow-up**: closed (Won/Lost) → none; override → override; Meeting Set / Office Visit — Scheduled →
  meeting time if given else last activity + 1; else (last activity or lead date) + default days.
* **Priority**: CLOSED / OVERDUE / TODAY / HIGH (Interested, Meeting, Proposal, Negotiation) / NORMAL.
* **Data checks**: imported-phone review, invalid phone, duplicate phone, missing closed date,
  won without deal value, lost without reason, scheduled visit without date.
* **Closed date** stamped when stage becomes Won/Lost; cleared on re-open; *Office Visit — Time Pending*
  clears the meeting date.
* **Call Log**: types Follow-up, Initial, Count update, Count correction; save is idempotent (call id),
  updates lead outcome / next follow-up / last activity. Editing *Calls Made* posts a delta row
  (Count update / Count correction) so history is never lost; fractional/negative totals rejected.
* **Daily slots**: each day ensures *N* blank lead rows; yesterday's untouched blanks are reused first.
* **Reports**: Daily (previous day, after midnight), Monthly (closed month on 1st), Yearly (1 Jan);
  periods clipped to launch date; snapshot hash for de-duplication; PDFs archived privately.
* **Dashboards**: Today (new leads, due/overdue, meetings), Follow-ups queue, Meetings, Conversion,
  Analytics (funnel, owner, channel, service, industry).

Current snapshot (2 Nov 2026): **142 leads, 93 active, 0 won, 81 due/overdue, 7 time-pending visits**.

---

## 4. Golden values (Monthly_Statements, BDT)

| Line | Apr | May | Jun | Jul | Aug | Sep | Oct | Nov |
|---|---|---|---|---|---|---|---|---|
| Total revenue | 85,000 | 181,600 | 101,000 | 10,000 | 0 | 64,000 | 0 | 0 |
| Total expenses | 260,764 | 341,515 | 331,471 | 294,142 | 288,952 | 273,097 | 232,140 | 2,200 |
| Net income | −175,764 | −159,915 | −230,471 | −284,142 | −288,952 | −209,097 | −232,140 | −2,200 |
| Ending cash | 6,490 | 300,936 | 13,350 | 240 | 223,898 | −1,000 | −62,160 | −62,160 |
| A/R | 170,200 | 244,800 | 268,800 | 145,800 | 133,800 | 163,800 | 150,800 | 150,800 |
| Total assets | 305,136 | 714,221 | 472,750 | 288,608 | 483,056 | 289,943 | 247,600 | 245,400 |
| Total liabilities | 142,000 | 11,000 | 0 | 100,000 | 200,000 | 215,984 | 405,781 | 405,781 |
| Total equity | 163,136 | 703,221 | 472,750 | 188,608 | 283,056 | 73,959 | −158,181 | −160,381 |
| Balance check | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |

The full 50-line fixture lives in `tests/Fixtures/golden_monthly_statements.json`.

## 5. Known issues found during analysis (carried into the SRS as requirements)

1. Fixed row capacities (1000/2000/2600 rows) — removed in a database.
2. Text-matching links (source name → revenue id) are fragile — replaced with foreign keys; aliases kept
   in `source_aliases` for import and search.
3. Month packs are copied tabs — replaced by on-demand statements per period with *period close/lock*.
4. One October salary missing (Rajib) and 16 historical projects lack delivery evidence — surfaced in
   the Controls page, not silently fixed.
5. The bank overdraft is a book figure — the system adds bank-account reconciliation (new feature).
6. Partnerships automation was **not installed** — implemented natively.
7. CRM PDFs require Drive; replaced by browser-side PDF generation and an immutable snapshot archive.
8. Notifications were only cell texts (`DRAFT`, `NOT SAVED`, `REVIEW`) — replaced by a real notification system
   (toasts, notification centre, Pusher realtime, SMTP digests).
