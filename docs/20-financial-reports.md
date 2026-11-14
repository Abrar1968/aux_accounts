# 20 — Profit & Loss, Cash Flow Statement, Trial Balance

Accounting → **Reports** (permission `accounting.reports.view`). All three are built from the monthly
statements (`FinancialStatementService`, golden-tested to the paisa against the workbook) by
`FinancialReportService`. Each report carries **self-checks that must be 0.00**. They are printed at the
bottom of every report, PDF included.

**Ranges**: presets (this month, last month, this quarter, financial year to date, previous FY, since
start) or any From/To month. The financial year starts in **July** (Bangladesh, July–June); change it
in Settings → Policies (`accounting.fiscal_year_start`, 1–12). Nothing goes back before the model start
(Apr 2026).

## Profit & Loss
Revenue (project · marketing · partnership) − Staff costs − Sales & marketing − Office & operations −
Revenue share = **EBITDA**; − Depreciation = **Net profit**. You can compare it with the previous
period of the same length or the same months last year (only when those months exist), and view it
by month. Checks:
- Net profit = Σ monthly net income.
- The revenue groups = total revenue.
- The expense groups = total expenses.

## Cash flow statement (IAS 7)
| Section | Lines |
|---|---|
| Operating | customer receipts; salaries; referral commissions; operating expenses paid; employee advances given; Siam revenue share paid (`LIA-SIAM-SHARE`) |
| Investing | fixed assets bought for cash |
| Financing | owner capital in; owner distributions; loans / contract funding received; loan, dues & financing repayments |

- **Indirect reconciliation**: net profit + depreciation − Δ receivables − Δ employee advances − Δ prepaid
  & other assets + Δ customer advances + Δ payables.
- **Non-cash transactions** are disclosed, not counted: share reclassifications, assets bought on
  credit, and liabilities without cash.

Checks:
- Net change = Σ monthly net cash movement.
- Opening + change = closing cash.
- **Indirect operating = direct operating.**

## Trial balance
- Closing balances of every balance-sheet account at the chosen month end. Fixed assets are shown at
  cost with accumulated depreciation separately. Negative balances move to the other column.
- It also includes the income and expense accounts for the financial year to date (or since the
  start), plus retained earnings brought forward.

Checks:
- Debits = credits.
- Income − expenses = P&L net profit.
- Retained earnings b/f + profit = cumulative net income.
- Cost − accumulated depreciation = net fixed assets.

## Engine fixes made while building these
- Owner **Distributions** (capital rows with negative cash) now leave the bank in the cash
  reconciliation (`Owner Distributions / Capital Cash Out`). Before, they reduced equity but not cash.
- Liabilities under **any other code** (e.g. a new bank loan `LIA-BANK-LOAN`) now appear on the
  balance sheet as `Other Liabilities / Loans`. Before, only the built-in codes were shown.

Both were zero in the seeded data, so every golden figure is unchanged.

## Verification (tests/Feature/Services/FinancialReportsTest.php)
- All 36 From–To combinations of Apr–Nov 2026: every check of every report is 0.00, and the opening
  balance sheet (31 Mar 2026) balances.
- P&L, cash flow and trial balance totals equal the golden workbook statements.
- They also equal raw ledger sums computed independently in SQL: revenue, salaries, referral
  commissions, depreciation (2,200/month), receipts, expense payments, advances, capital, the Siam
  installments and repayments.
- After a distribution, a new loan with a repayment, and a laptop bought on credit, the balance sheet
  still balances, financing moves by exactly +130,000, operating and investing are unchanged, the
  non-cash items are disclosed, depreciation rises by 1,000, and the trial balance stays balanced.
