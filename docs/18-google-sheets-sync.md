# 18 — Google Sheets → Lead CRM live sync

Your "Automated daily lead sheet" stays where the team works. Every edit there reaches
Auxtech Accounts within seconds.

## How it works
```
Lead sheet (Leads, Call Log)
   │  Apps Script "AuxtechLeadSync" (installable onEdit trigger + full re-sync every 5 min)
   │  POST JSON, signed: X-Auxtech-Signature = HMAC-SHA256("<timestamp>.<body>", secret)
   ▼
https://accounts.auxtechsoftware.com/api/integrations/google-sheets/leads
   │  GoogleSheetSyncService
   ▼
leads / lead_calls  (stage, score, priority, follow-ups recomputed by LeadComputer)
```

**Push model.** The server holds no Google credentials, and the sheet stays private. The only shared item
is a 48-character secret, stored encrypted in the database. A request that is unsigned, carries a wrong
signature, or is more than 10 minutes old is refused with 401.

**Leads.**
- Rows are matched by **Lead ID**, then by normalised phone.
- New rows create leads, keeping the sheet's Lead ID. Changed rows update the CRM.
- Unchanged rows are skipped by content hash, so the 5-minute re-sync is cheap (about 100 ms for 200 rows).
- **Owned columns:** the sheet owns Lead Date, Phone, Business type, Industry, Service, Source, Outcome,
  Calls Made, Owner, Quoted Price, Follow-up Override, Notes, Meeting date and details, Deal Value,
  Lost Reason, and Last Activity.
- **Derived columns:** Stage, Score, Priority, Next Follow-up, Closed Date and WhatsApp are recomputed by
  the CRM. A real-data check against all 192 sheet rows gave 192/192 identical stage, score and next
  follow-up values.
- **Picklists:** new Service, Source, Business type, Industry and Owner values join the CRM picklists automatically.

**Call Log.**
- Saved rows are inserted once each, by **Call ID**.
- Draft (unsaved) rows wait until the sheet saves them.

**Never deleted.** Removed sheet rows are left alone. Leads archived in the CRM stay archived, even if
the sheet row changes.

**Bad rows** (invalid phone, unknown outcome):
- are skipped and listed under Settings → Integrations, with their sheet row number;
- are written to the automation log;
- do not block the other rows.

## Set-up (one time, about 2 minutes)
1. Auxtech Accounts → **Settings → Integrations → Enable sync**.
2. Click **Download Apps Script** (`AuxtechLeadSync.gs`). Your URL and secret are already filled in.
3. In the lead sheet, go to **Extensions → Apps Script → + → Script**, name it `AuxtechLeadSync`, paste the file and save.
4. Choose **auxtechSetup** in the toolbar, click **Run**, and allow access.
   This installs the triggers and sends everything once.
5. Settings → Integrations should now show **Connected**, with the counts from the first sync.

**Rotate secret** issues a new secret, and the old script stops working immediately. Download the
script again and replace the old file in the sheet.

## Troubleshooting
| Symptom | Fix |
|---|---|
| Apps Script log says `401 Invalid signature` | The secret was rotated: download the script again and replace it |
| `401 Stale or missing timestamp` | The server clock is wrong (check the hosting time) |
| Status says "No sync in the last 15 min" | In Apps Script, open Triggers: both `auxtechOnEdit` and `auxtechSyncAll` must exist. Run `auxtechSetup` again |
| A row is listed under "Rows that need fixing" | Correct that row in the sheet (phone or outcome); the next edit or 5-minute sync retries it |
