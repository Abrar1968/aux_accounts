# 15 — New Features (Phase 4)

| Feature | SRS | Where | Notes |
|---|---|---|---|
| Bank & cash accounts + reconciliation | FR-ACC-86 | Accounting → Bank & cash (`/accounting/banking`) | `BankingService` allocates every cash movement used by the statement to an account by matching the entry's *account*, then *method*, then liability source text against account aliases. Unmatched entries stay **Unassigned** (most pre-October salaries/bills have no account). Invariant (tested every month): Σ accounts + unassigned = statement ending cash. Reconcile = enter the bank/app balance for a date → book balance, difference, matched/difference status, history. Seeded accounts: DBBL, EBL, bKash, Cash. |
| Budgets vs actual | FR-ACC-84 | Overview → Budgets | Budget per income-statement line per month; actual from `FinancialStatementService`; variance, %, favourable flag (revenue above / cost below budget); copy last month; chart of expense lines. |
| 90-day cash-flow forecast | FR-ACC-85 | Overview → Cash forecast | Weekly, from today's book cash. Assumptions shown on the page: open A/R × `forecast.collection_rate` (Settings, default 60 %) over 4 weeks; unpaid bills/salary in week 1; payroll (active employees' base salary) at each month end; recurring templates on their day. |
| Recurring bill templates | FR-ACC-53 | Spending → Recurring bills | One unpaid bill per template per month, created by `accounting:daily` (or "Generate"); idempotent, skips closed periods, respects start/end month, clamps day to month end. |
| Customer statement (print + PDF) | FR-ACC-32 | Receivables → click a customer | Opening due, recognised revenue, receipts (cash + withheld fee), running balance; closing equals the receivable balance (tested). |
| Caller targets & leaderboard | FR-CRM-14 | CRM → Leaderboard | Monthly calls / new leads / wins per owner vs targets; score = mean achievement of set targets (capped 150 %). Permission `crm.targets.manage` to edit targets. |
| Lead CSV import with duplicate preview | FR-CRM-15 | CRM → Import leads | Upload → preview classifies rows (new, already in CRM, duplicate in file, invalid phone; unknown outcome → New); only *new* rows can be imported, through `LeadService` (same IDs/derivations). Preview cached 30 min per user. Template CSV download. |
| Payslips | FR-ACC-65 | Payroll → Payslip | Delivered in Phase 3 (print/PDF). |
| A/R aging | FR-ACC-32 | Receivables → Aging | Delivered in Phase 1/3. |
| Won lead → Project | FR-CRM-10 | Lead page → Convert to project | Delivered in Phase 1/3. |

New permissions: `accounting.banking.view|manage|reconcile`, `accounting.budgets.view|manage`, `crm.targets.manage`,
`crm.leads.import` (owner via `*`, accountant via `accounting.banking.*` / `accounting.budgets.*`, sales manager via `crm.*`).

Tests: `tests/Feature/Phase4FeaturesTest.php` (9 tests). QA: the 8 new pages render at 360/768/1440 px without errors
or overflow.
