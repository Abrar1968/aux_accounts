// Shared scale helpers for the SVG charts (no chart library).
export function niceTicks(min, max, count = 4) {
    if (min === max) { max = min + 1; }
    const span = max - min;
    const step0 = span / count;
    const mag = 10 ** Math.floor(Math.log10(Math.abs(step0)));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= step0) ?? step0;
    const lo = Math.floor(min / step) * step, hi = Math.ceil(max / step) * step;
    const ticks = [];
    for (let v = lo; v <= hi + step / 2; v += step) ticks.push(+v.toFixed(10));
    return ticks;
}
export const compact = (n) => {
    const a = Math.abs(n);
    const s = a >= 1e7 ? `${(a / 1e7).toFixed(1)}Cr` : a >= 1e5 ? `${(a / 1e5).toFixed(a >= 1e6 ? 0 : 1)}L` : a >= 1e3 ? `${(a / 1e3).toFixed(0)}k` : `${a}`;
    return n < 0 ? `-${s}` : s;
};
export const SERIES = ['var(--color-brand-500)', 'var(--color-positive)', 'var(--color-warning)', 'var(--color-negative)', '#0d9488', '#8b5cf6'];
