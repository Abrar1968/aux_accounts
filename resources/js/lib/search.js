// Client-side fuzzy-ish search shared by pickers and the command palette:
// every word must appear somewhere (any order), case/accent-insensitive; phone numbers match
// with or without spaces, dashes, +880 / leading 0. Results are ranked (prefix > word start > anywhere).
const fold = (s) => String(s ?? '').normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase();
const digits = (s) => String(s ?? '').replace(/\D+/g, '');

export function tokens(query) {
    const out = [];
    for (const w of fold(query).split(/\s+/).filter(Boolean)) {
        const num = /^[+\d\-().]+$/;
        if (out.length && num.test(w) && num.test(out.at(-1))) out[out.length - 1] += w; // "+880 1811-222333" = one number
        else out.push(w);
    }
    return out;
}

function tokenScore(hay, hayDigits, t) {
    const i = hay.indexOf(t);
    if (i === 0) return 3;
    if (i > 0) return /[\s·.@\-_/(]/.test(hay[i - 1]) ? 2 : 1;
    const d = digits(t).replace(/^(880|0)+/, '');
    return d.length >= 4 && hayDigits.includes(d) ? 1 : 0;
}

/** Score `text` against `query` (0 = no match). */
export function score(text, query) {
    const ts = tokens(query);
    if (!ts.length) return 1;
    const hay = fold(text);
    const hd = digits(text);
    let total = 0;
    for (const t of ts) {
        const s = tokenScore(hay, hd, t);
        if (!s) return 0;
        total += s;
    }
    return total;
}

/** Filter + rank `items` by `query`; `fields(item)` returns the searchable text. */
export function search(items, query, fields, limit = Infinity) {
    if (!tokens(query).length) return items.slice(0, limit);
    return items.map((it, i) => ({ it, i, s: score(fields(it), query) }))
        .filter((x) => x.s > 0)
        .sort((a, b) => b.s - a.s || a.i - b.i)
        .slice(0, limit)
        .map((x) => x.it);
}

/** Wrap matched words in <mark> for highlighting (input is escaped). */
export function highlight(text, query) {
    const esc = String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const ts = tokens(query).filter((t) => t.length > 1).map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    if (!ts.length) return esc;
    return esc.replace(new RegExp(`(${ts.join('|')})`, 'gi'), '<mark class="rounded bg-brand-400/25 px-0.5 text-inherit">$1</mark>');
}
