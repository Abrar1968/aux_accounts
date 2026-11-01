# 05 — Routes & Inertia Pages

All routes are in `routes/web.php`, session-authenticated (`auth` middleware) and guarded with
`permission:<perm>`. Writes redirect back with a `toast` flash. Page components live in
`resources/js/Pages/<Module>/<Page>.vue`.

## 1. Conventions
* Resource routes: `index` (list + filters in query string), `create`/`edit` are **slide-over drawers**
  on the index page (no separate pages on mobile → full-screen sheet), `show` = 360° page.
* Query params: `q`, `from`, `to`, `status`, `sort` (`-paid_at`), `page`, `per_page`.
* Posting endpoints accept `request_id` (UUID generated in the browser when the form opens) → safe retry.
* Month params use `YYYY-MM`.

## 2. Route map

| Method | URI | Name | Page / action | Permission |
|---|---|---|---|---|
| GET | /login · POST /login · POST /logout | login… | Auth/Login | guest |
| GET | /forgot-password, /reset-password/{token} | password.* | Auth/* | guest |
| GET | / | dashboard | Dashboard | dashboard.view |
| GET | /accounting/statements/{month?} | statements.show | Accounting/Statements/Show (pack: IS, cash, BS) | accounting.reports.view |
| GET | /accounting/statements/grid | statements.grid | Accounting/Statements/Grid (multi-month) | accounting.reports.view |
| GET | /accounting/controls | controls | Accounting/Controls | accounting.reports.view |
| resource | /accounting/projects | projects.* | Accounting/Projects/Index, Show | accounting.projects.* |
| POST | /accounting/projects/{project}/deliver | projects.deliver | mark delivered | accounting.projects.update |
| resource | /accounting/recognition | recognition.* | Accounting/Recognition/Index | accounting.projects.* |
| resource | /accounting/payments | payments.* | Accounting/Payments/Index | accounting.payments.* |
| GET | /accounting/receivables | receivables | Accounting/Receivables (month-end, aging) | accounting.payments.view |
| resource | /accounting/marketing | marketing.* | Accounting/Marketing/Index | accounting.projects.* |
| GET/POST | /accounting/partnerships/* | partnerships.* | Accounting/Partnerships/{Collaborators,Packages,Revenue} | accounting.projects.* |
| resource | /accounting/expenses | expenses.* | Accounting/Expenses/Index, Show | accounting.expenses.* |
| POST | /accounting/expenses/{expense}/payments | expenses.pay | PaymentPostingService (expense) | accounting.expenses.pay |
| resource | /accounting/employees | employees.* | Accounting/Employees/Index | accounting.payroll.* |
| resource | /accounting/payroll | payroll.* | Accounting/Payroll/Index (month view) | accounting.payroll.* |
| POST | /accounting/payroll/{month}/draft | payroll.draft | draft from base salaries | accounting.payroll.create |
| POST | /accounting/payroll/{payroll}/payments | payroll.pay | PaymentPostingService (salary) | accounting.payroll.pay |
| GET | /accounting/payroll/{payroll}/payslip | payroll.payslip | Accounting/Payroll/Payslip (print/PDF) | accounting.payroll.view |
| resource | /accounting/advances | advances.* | Accounting/Advances/Index | accounting.payroll.* |
| GET/PUT | /accounting/referrals | referrals.* | Accounting/Referrals/Index | accounting.payroll.* |
| resource | /accounting/assets | assets.* | Accounting/Assets/Index (+schedule) | accounting.ledger.* |
| resource | /accounting/liabilities | liabilities.* | Accounting/Liabilities/Index (+Siam tracker) | accounting.ledger.* |
| resource | /accounting/capital | capital.* | Accounting/Capital/Index (cap table) | accounting.ledger.* |
| POST | /accounting/periods/{month}/close · /reopen | periods.* | PeriodService | accounting.periods.manage |
| GET | /crm | crm.today | Crm/Today | crm.leads.view |
| resource | /crm/leads | leads.* | Crm/Leads/Index, Show | crm.leads.* |
| POST | /crm/leads/{lead}/calls | leads.calls.store | CallLogService | crm.calls.create |
| PUT | /crm/leads/{lead}/calls-made | leads.calls-made | count update/correction | crm.calls.create |
| POST | /crm/leads/{lead}/convert | leads.convert | create Project from won lead | accounting.projects.create |
| GET | /crm/pipeline · PUT /crm/pipeline/{lead} | pipeline.* | Crm/Pipeline (Kanban drag) | crm.leads.update |
| GET | /crm/follow-ups · /crm/meetings | crm.* | Crm/FollowUps, Crm/Meetings | crm.leads.view |
| GET | /crm/reports · /crm/reports/{report} | crm.reports.* | Crm/Reports/Index, Show (print/PDF) | crm.reports.view |
| GET | /crm/analytics | crm.analytics | Crm/Analytics | crm.reports.view |
| GET/PUT | /settings/* | settings.* | System/Settings (policies, CRM lists, categories) | settings.manage |
| resource | /system/users · /system/roles | users.*, roles.* | System/Users, System/Roles | users.manage |
| GET | /system/automation | automation | System/Automation (runs, health) | settings.manage |
| GET | /system/audit | audit | System/Audit | audit.view |
| GET | /notifications · POST /notifications/{id}/read · POST /notifications/read-all | notifications.* | Notifications/Index | auth |
| GET/PUT | /profile, /profile/notifications | profile.* | Profile/Edit, Profile/Notifications | auth |
| POST | /attachments · DELETE /attachments/{id} · GET /attachments/{id} (signed) | attachments.* | AttachmentService (GD) | per parent |
| GET | /ajax/search?q= | ajax.search | JSON: global search (Ctrl K) | auth |
| GET | /ajax/receivables?q= | ajax.receivables | JSON: receivable picker | accounting.payments.create |
| GET | /ajax/leads/phone-check?phone= | ajax.phone | JSON: normalised phone + duplicates | crm.leads.create |
| POST | /broadcasting/auth | — | Pusher private channel auth | auth |

## 3. Shared Inertia props (HandleInertiaRequests)
`app{name,timezone,currency}`, `auth.user{id,name,email,avatar,role,permissions}`, `flash.toast`,
`notifications.unread`, `pusher{key,cluster}`.

## 4. Permission catalogue (seeded roles)
| Role | permissions |
|---|---|
| owner | `*` |
| accountant | `dashboard.view`, `accounting.*`, `attachments.*` (not `accounting.periods.manage` reopen) |
| sales_manager | `dashboard.view`, `crm.*`, `accounting.reports.view`, `accounting.projects.create` |
| caller | `crm.leads.view`, `crm.leads.create`, `crm.leads.update`, `crm.calls.create` (own leads) |
| viewer | `dashboard.view`, `accounting.reports.view`, `accounting.ledger.view` |
