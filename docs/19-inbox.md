# 19 — Inbox (client replies) · partnership & referral emails · referrer picker

## Inbox (System → Inbox, Ctrl+Shift+M)
Every email sent from the app uses the company mailbox (`MAIL_FROM_ADDRESS`) as its Reply-To, so client
answers land in that mailbox. The app reads it and shows the conversations here.

- **Reader**: a small IMAP4rev1 client written in-house (`app/Services/Mail/ImapClient.php` over an
  implicit-TLS socket, port 993). No PHP `imap` extension or package is needed. It opens the folder with
  `EXAMINE` and fetches with `BODY.PEEK[]`, so it is **read-only**: nothing on the server is changed,
  and webmail/Outlook keep their own read state.
- **Login**: `MAIL_HOST` / `MAIL_USERNAME` / `MAIL_PASSWORD` are reused. Override with `IMAP_HOST`,
  `IMAP_PORT` (993), `IMAP_USERNAME`, `IMAP_PASSWORD`, `IMAP_FOLDER` (INBOX), `IMAP_VERIFY_PEER` (true),
  `IMAP_INITIAL_DAYS` (30 — how far back the first import goes) and `IMAP_MAX_MESSAGE_MB` (20; larger
  messages are imported as headers only).
- **Schedule**: `mail:fetch` runs every minute from the existing cron (`schedule:run`), at most 40
  messages per run. "Check now" on the page runs it immediately.
- **Storage**: every message is stored once (`mail_messages`, de-duplicated by UID and Message-ID).
  Attachments go to `storage/app/private/mail/{id}/` (included in the Home Directory backup).
- **Threading**: each sent email gets its own `Message-ID`. A reply is linked through `In-Reply-To` /
  `References`, falling back to a "Re:" subject from an address we emailed. A thread shows the email we
  sent, every reply in and every reply out, by date.
- **Reading**: the plain text is shown first, with quoted history folded away. "Original layout"
  renders the HTML in a sandboxed frame (no scripts; `Content-Security-Policy: sandbox`). Inline
  images are embedded.
- **Replying**: replies use the same branded template, signed with the Settings signature, sent with
  `In-Reply-To` / `References` (so the client's mail app threads them), and logged in Sent emails as
  "Inbox reply".
- **Views**: Inbox · Replies to us · Unread · Archived, with word-by-word search. Unread messages show
  a badge on the Inbox menu item and a dot on the System rail icon. New mail notifies Owner, Accountant
  and Sales manager (bell + realtime; type "Email reply received").
- **Permission**: `email.inbox` (Users & roles). It is granted to every role that can send email.
- **Health**: `ops:check` now logs into the mailbox and shows the message count. The Inbox page shows
  the last check time and the last error, if there was one.

## New email templates (System → Send email)
- **Partnership statement** (record: a collaborator). For each month: package, earned (or Pending)
  and received. Totals: earned, received, balance due / advance. The address comes from any email in
  the collaborator's Contact field.
- **Referral commission memo** (record: a referral). Shows the project, client, project value, the
  commission (noting an equal split when there are several referrers), paid and due. When the person
  has several referrals, every referral is listed in a table. The address comes from the matching user
  account.

## Referrer picker (project form)
Referrers are chips you pick from a list: past referrers first (by number of referrals), then
employees and team members. Type a new name and press Enter (or just Save) to add someone new. The
stored value is still "Rizwan, Abrar", so the equal memo split is unchanged.
