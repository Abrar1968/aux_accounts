// Formatting helpers (BDT, Asia/Dhaka). Pages never format inline — they call these.
const TZ = 'Asia/Dhaka';
const grouping = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2, minimumFractionDigits: 0 });
const grouping2 = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2, minimumFractionDigits: 2 });

export const toNumber = (v) => (v === null || v === undefined || v === '' ? 0 : Number(v));

/** ৳1,23,456 — negatives in parentheses (accounting style). */
export function money(v, { decimals = false, sign = false, compact = false, empty = '—' } = {}) {
    if (v === null || v === undefined || v === '') return empty;
    const n = toNumber(v);
    let body;
    if (compact && Math.abs(n) >= 100000) body = `${(Math.abs(n) / 100000).toFixed(Math.abs(n) >= 1e7 ? 0 : 1)}L`;
    else if (compact && Math.abs(n) >= 1000) body = `${(Math.abs(n) / 1000).toFixed(Math.abs(n) >= 1e4 ? 0 : 1)}k`;
    else body = (decimals ? grouping2 : grouping).format(Math.abs(n));
    if (n < 0) return `(৳${body})`;
    return `${sign && n > 0 ? '+' : ''}৳${body}`;
}

export const num = (v) => grouping.format(toNumber(v));
export const pct = (v, digits = 0) => (v === null || v === undefined ? '—' : `${(toNumber(v) * 100).toFixed(digits)}%`);

/** Parses `Y-m-d` as a local calendar date and ISO strings as instants. */
export function parseDate(v) {
    if (!v) return null;
    if (v instanceof Date) return v;
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(v));
    if (m) return new Date(Date.UTC(+m[1], +m[2] - 1, +m[3], 6)); // noon-ish in Dhaka, never shifts the day
    const d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d) ? null : d;
}

const fmt = (opts) => new Intl.DateTimeFormat('en-GB', { timeZone: TZ, ...opts });
const dFmt = fmt({ day: '2-digit', month: 'short', year: 'numeric' });
const dtFmt = fmt({ day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
const mFmt = fmt({ month: 'short', year: 'numeric' });
const shortFmt = fmt({ day: '2-digit', month: 'short' });

export const date = (v) => (parseDate(v) ? dFmt.format(parseDate(v)) : '—');
export const shortDate = (v) => (parseDate(v) ? shortFmt.format(parseDate(v)) : '—');
export const dateTime = (v) => {
    const d = parseDate(v);
    if (!d) return '—';
    return /^\d{4}-\d{2}-\d{2}$/.test(String(v)) ? dFmt.format(d) : dtFmt.format(d);
};
export const monthLabel = (v) => {
    if (!v) return '—';
    const s = String(v);
    return mFmt.format(parseDate(/^\d{4}-\d{2}$/.test(s) ? `${s}-01` : s));
};

export function relative(v) {
    const d = parseDate(v);
    if (!d) return '—';
    const s = Math.round((Date.now() - d.getTime()) / 1000);
    const abs = Math.abs(s);
    const unit = abs < 60 ? [s, 'second'] : abs < 3600 ? [Math.round(s / 60), 'minute'] : abs < 86400 ? [Math.round(s / 3600), 'hour'] : [Math.round(s / 86400), 'day'];
    return new Intl.RelativeTimeFormat('en', { numeric: 'auto' }).format(-unit[0], unit[1]);
}

/** Today in Dhaka as Y-m-d / Y-m (for form defaults). */
export const todayIso = () => new Intl.DateTimeFormat('en-CA', { timeZone: TZ }).format(new Date());
export const monthIso = () => todayIso().slice(0, 7);
/** Current Dhaka time as `YYYY-MM-DDTHH:mm` for datetime-local inputs. */
export const nowLocalIso = () => new Intl.DateTimeFormat('sv-SE', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date()).replace(' ', 'T');

const TONES = {
    positive: ['OK', 'Paid', 'Settled', 'Won', 'Active', 'CLOSED_GOOD', 'saved', 'ok', 'Delivered', 'PAID', 'Saved'],
    negative: ['REVIEW', 'Overpaid', 'Unpaid', 'Lost', 'OVERDUE', 'error', 'closed', 'Invalid progress', 'Disposed'],
    warning: ['INPUT REQUIRED', 'EVIDENCE REVIEW', 'ACCOUNTING REVIEW', 'Partially Paid', 'Partly settled', 'Open', 'TODAY', 'Draft', 'IN_PROGRESS', 'PARTIAL', 'running', 'In progress', 'Ending progress required'],
    info: ['HIGH', 'Meeting', 'Proposal', 'Negotiation', 'Interested', 'historical', 'open', 'NORMAL', 'Contacted', 'Delivery not dated'],
};
export function tone(status) {
    if (status === null || status === undefined || status === '') return 'neutral';
    for (const [t, list] of Object.entries(TONES)) if (list.includes(String(status))) return t;
    return 'neutral';
}

export const titleCase = (s) => String(s ?? '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
export const initials = (name) => String(name ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
