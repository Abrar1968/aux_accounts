# 07 — Notifications & Realtime (Pusher, database, SMTP)

## 1. Channels
| Channel | Transport | Purpose |
|---|---|---|
| Toast | Inertia flash `toast{type,title,message}` | immediate feedback for the user's own action |
| In-app | Laravel `database` notifications → `notifications` table | bell badge + Notification centre |
| Realtime push | Pusher (`broadcast` channel on notifications + `ShouldBroadcastNow` events) | live toast + badge without refresh; dashboards refresh when ledgers change |
| Email | SMTP (`mail` channel, queued on `database` queue) | digests, reminders, failures |

Pusher app: id `2208649`, cluster `ap2`, key `a91f1ba8822616d04f60` (public); secret only in `.env`
(`PUSHER_APP_SECRET`). Verified: a test event was accepted by the ap2 cluster during setup.

## 2. Private channels (`routes/channels.php`)
* `private-auxtech` — any active user; events: `ledger.changed` (PaymentPosted), `period.closed`,
  `lead.updated`, `automation.finished`.
* `private-App.Models.User.{id}` — the user's own notifications (Laravel convention).
* Auth endpoint `/broadcasting/auth` (session + CSRF) registered by `withBroadcasting()`.

## 3. Notification catalogue
| Type (class) | Trigger | Recipients | Default channels |
|---|---|---|---|
| PaymentRecorded | customer/expense/salary payment posted | owner, accountant | in-app, push |
| PostingRejected | posting blocked (overpayment, closed period) | actor | toast + in-app |
| FollowUpsDue | 08:30 daily, leads due/overdue | each lead owner | in-app, push, email |
| MeetingSoon | 60 min before `meeting_at` (5-min safety check) | lead owner | in-app, push |
| ReceivableOverdue | A/R > 30 days without receipt (daily) | owner, accountant | in-app, email (weekly) |
| PayrollUnpaid | payroll unpaid after 7th of next month; missing payroll row | owner, accountant | in-app, email |
| PeriodCloseReminder | 25th: checklist (drafts, controls, unreconciled) | owner, accountant | in-app, email |
| ControlFailed | daily automation finds REVIEW / INPUT REQUIRED | owner, accountant | in-app, push |
| AutomationFailed | any scheduled command errors | owner | in-app, push, email |
| LeadAssigned | lead owner changed | new owner | in-app, push |
| DealWon | lead stage → Won | owner, sales manager | in-app, push |
| DailyCrmDigest | 08:35 per caller | callers | email |
| CrmReportReady | daily/monthly/yearly snapshot created | sales manager | in-app |

Each notification's `toArray()` payload: `{title, message, level(info|success|warning|error), url, icon, meta}`.

## 4. Preferences
`notification_preferences(user_id, type, in_app, email, push)`; missing row = catalogue default.
`NotificationService::send($type, $users, $notification)` filters channels per user (`via()` reads
preferences). Profile → Notifications page lets each user toggle channels per type. Owners cannot disable
`AutomationFailed` in-app.

## 5. Frontend
* `AppLayout` subscribes: `Echo.private('auxtech')` events → toast + `router.reload({only: [...]})`;
  `Echo.private('App.Models.User.'+id).notification(...)` → badge++ and toast.
* `NotificationBell` dropdown (latest 10, mark read), `/notifications` page (filters: unread/all/type,
  bulk mark-read), deep links via `url`.
* Toasts: top-right desktop, bottom-centre mobile, 5 s, `aria-live=polite`, error toasts persist until dismissed.

## 6. Delivery without a worker
Broadcast events use `ShouldBroadcastNow` (sync HTTP to Pusher). Mail is queued and drained by
`queue:work --stop-when-empty` every minute from the scheduler. Failed jobs → `failed_jobs` + AutomationFailed.

## 7. Testing
`Notification::fake()`, `Event::fake([PaymentPosted::class])`, assert channels per preference; Pusher
calls are faked by `BROADCAST_CONNECTION=null` in `phpunit.xml`.
