/**
 * {{APP}} — Lead CRM live sync (Google Sheets → {{APP}})
 *
 * HOW TO INSTALL (one time, about 2 minutes):
 *   1. In the lead sheet: Extensions → Apps Script.
 *   2. Click "+" → Script, name it AuxtechLeadSync, paste this whole file, Save.
 *   3. Choose the function "auxtechSetup" in the toolbar and click Run. Allow the permissions
 *      Google asks for (it needs to read this spreadsheet and call {{APP}}).
 *   4. Done. Every edit in "Leads" or "Call Log" is sent instantly, and a full re-sync runs every
 *      5 minutes so changes made by formulas or other scripts are picked up too.
 *
 * Your existing CRM automation keeps working; this file only reads the sheet.
 * Keep this file private: the secret below lets the sheet write to your CRM.
 * To test: run auxtechSyncAll and look at View → Executions (or Settings → Integrations in {{APP}}).
 */
var AUXTECH_ENDPOINT = '{{ENDPOINT}}';
var AUXTECH_SECRET = '{{SECRET}}';
var AUXTECH_TZ = 'Asia/Dhaka';
var AUXTECH_SHEETS = { 'Leads': 'leads', 'Call Log': 'calls' };
var AUXTECH_BATCH = 250;

/** Run once: installs the edit trigger + 5-minute re-sync and sends everything now. */
function auxtechSetup() {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (['auxtechOnEdit', 'auxtechSyncAll'].indexOf(t.getHandlerFunction()) >= 0) ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('auxtechOnEdit').forSpreadsheet(SpreadsheetApp.getActive()).onEdit().create();
  ScriptApp.newTrigger('auxtechSyncAll').timeBased().everyMinutes(5).create();
  auxtechSyncAll();
}

/** Installable onEdit trigger: sends only the edited rows. */
function auxtechOnEdit(e) {
  if (!e || !e.range) return;
  var sheet = e.range.getSheet();
  var kind = AUXTECH_SHEETS[sheet.getName()];
  if (!kind) return;
  var table = auxtechTable_(sheet);
  if (!table) return;
  var first = Math.max(e.range.getRow(), table.headerRow + 1);
  var last = e.range.getLastRow();
  if (last < first) return;
  var body = {};
  body[kind] = auxtechRows_(sheet, table, first, last, kind);
  if (body[kind].length) auxtechSend_(body, 'edit');
}

/** Time trigger (every 5 minutes) and manual full sync. */
function auxtechSyncAll() {
  var ss = SpreadsheetApp.getActive();
  ['Leads', 'Call Log'].forEach(function (name) {
    var sheet = ss.getSheetByName(name);
    if (!sheet) return;
    var kind = AUXTECH_SHEETS[name];
    var table = auxtechTable_(sheet);
    if (!table) return;
    var last = sheet.getLastRow();
    if (last <= table.headerRow) return;
    var rows = auxtechRows_(sheet, table, table.headerRow + 1, last, kind);
    for (var i = 0; i < rows.length; i += AUXTECH_BATCH) {
      var body = {};
      body[kind] = rows.slice(i, i + AUXTECH_BATCH);
      auxtechSend_(body, 'full');
    }
  });
}

/** Finds the header row (within the first 6 rows) by its "Lead ID" column. */
function auxtechTable_(sheet) {
  var lastCol = sheet.getLastColumn();
  var probeRows = Math.min(6, sheet.getLastRow());
  if (!lastCol || !probeRows) return null;
  var probe = sheet.getRange(1, 1, probeRows, lastCol).getDisplayValues();
  for (var i = 0; i < probe.length; i++) {
    var row = probe[i].map(function (h) { return String(h).trim(); });
    if (row.indexOf('Lead ID') >= 0 && (row.indexOf('Phone') >= 0 || row.indexOf('Phone (Auto)') >= 0)) {
      return { headerRow: i + 1, headers: row };
    }
  }
  return null;
}

/** Reads rows as {row, values:{header: value}}; dates are sent as Dhaka "yyyy-MM-dd HH:mm:ss". */
function auxtechRows_(sheet, table, first, last, kind) {
  var values = sheet.getRange(first, 1, last - first + 1, table.headers.length).getValues();
  var out = [];
  for (var r = 0; r < values.length; r++) {
    var o = {};
    for (var c = 0; c < table.headers.length; c++) {
      var h = table.headers[c];
      if (!h) continue;
      var v = values[r][c];
      if (v instanceof Date) v = Utilities.formatDate(v, AUXTECH_TZ, 'yyyy-MM-dd HH:mm:ss');
      o[h] = v;
    }
    var key = kind === 'calls' ? o['Call ID (Auto)'] : (o['Phone'] || o['Lead ID']);
    if (key !== '' && key !== null && key !== undefined && key !== false) out.push({ row: first + r, values: o });
  }
  return out;
}

/** Signed POST: X-Auxtech-Signature = hex(HMAC-SHA256("<timestamp>.<body>", secret)). */
function auxtechSend_(body, mode) {
  body.mode = mode;
  body.spreadsheet = SpreadsheetApp.getActive().getId();
  var json = JSON.stringify(body);
  var ts = String(Math.floor(Date.now() / 1000));
  var bytes = Utilities.computeHmacSha256Signature(ts + '.' + json, AUXTECH_SECRET, Utilities.Charset.UTF_8);
  var sig = bytes.map(function (b) { return ('0' + (b & 0xff).toString(16)).slice(-2); }).join('');
  var res = UrlFetchApp.fetch(AUXTECH_ENDPOINT, {
    method: 'post',
    contentType: 'application/json; charset=utf-8',
    payload: json,
    headers: { 'X-Auxtech-Timestamp': ts, 'X-Auxtech-Signature': sig },
    muteHttpExceptions: true,
  });
  var code = res.getResponseCode();
  if (code >= 300) console.error('Auxtech sync failed (' + code + '): ' + res.getContentText().slice(0, 500));
  else console.log('Auxtech sync ' + mode + ': ' + res.getContentText().slice(0, 300));
  return code;
}
