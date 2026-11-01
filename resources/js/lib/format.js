// BDT money / date helpers shared by every page (Asia/Dhaka).
const bdt = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

export function money(v, { sign = false } = {}) {
    const n = Number(v ?? 0);
    const s = `৳${bdt.format(Math.abs(n))}`;
    if (n < 0) return `(${s})`;
    return sign && n > 0 ? `+${s}` : s;
}

export function date(v, opts = { day: '2-digit', month: 'short', year: 'numeric' }) {
    if (!v) return '—';
    return new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Dhaka', ...opts }).format(new Date(v));
}

export const monthLabel = (v) => date(v, { month: 'short', year: 'numeric' });
