// Minimal PDF 1.4 writer (no third-party code). Supports Helvetica text, rules, shaded rows, JPEG
// images (company logo, accounts seal, client signature — the server stores a JPEG variant of every
// letterhead image) and a diagonal status watermark (PAID / PARTIALLY PAID / UNPAID).
// Helvetica has no ৳ glyph → "BDT".
const A4 = [595.28, 841.89];

function esc(s) {
    return String(s ?? '')
        .replace(/৳/g, 'BDT ')
        .replace(/[–—]/g, '-')
        .replace(/[·•]/g, '-')
        .replace(/[‘’]/g, "'").replace(/[“”]/g, '"').replace(/…/g, '...')
        .replace(/[^\x20-\x7E]/g, '?')
        .replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
}

/** Rough Helvetica width (pt) — enough for right-aligning numbers. */
const width = (s, size, bold = false) => {
    let w = 0;
    for (const ch of String(s)) w += /[A-Z]/.test(ch) ? 0.68 : /[0-9]/.test(ch) ? 0.56 : /[ il.,:;'|!]/.test(ch) ? 0.28 : /[mwMW]/.test(ch) ? 0.85 : 0.52;
    return w * size * (bold ? 1.05 : 1);
};

// drawn last with 16 % opacity (ExtGState /GS1) so it shows over images as well as text
const WATERMARK_TONES = { PAID: '0.05 0.55 0.25', 'PARTIALLY PAID': '0.85 0.50 0.0', UNPAID: '0.80 0.10 0.10' };

// Letterhead registered once by AppLayout from the shared Inertia prop (see lib/brand.js).
let brand = null;
export function setPdfBrand(b) { brand = b; }

async function fetchJpeg(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`image ${res.status}`);
    const bytes = new Uint8Array(await res.arrayBuffer());
    let bin = '';
    for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    return bin;
}

export class PdfDoc {
    constructor({ title = 'Report', margin = 40 } = {}) {
        this.title = title; this.margin = margin; this.pages = []; this.images = {}; this.mark = null; this.newPage();
    }
    newPage() { this.ops = []; this.pages.push(this.ops); this.y = A4[1] - this.margin; }
    ensure(h) { if (this.y - h < this.margin) this.newPage(); }
    get right() { return A4[0] - this.margin; }
    text(x, y, s, { size = 10, bold = false, align = 'left', gray = 0 } = {}) {
        const tx = align === 'right' ? x - width(s, size, bold) : align === 'center' ? x - width(s, size, bold) / 2 : x;
        this.ops.push(`${gray} g BT /${bold ? 'F2' : 'F1'} ${size} Tf ${tx.toFixed(2)} ${y.toFixed(2)} Td (${esc(s)}) Tj ET 0 g`);
    }
    line(x1, y1, x2, y2, gray = 0.85) { this.ops.push(`${gray} G 0.6 w ${x1} ${y1} m ${x2} ${y2} l S 0 G`); }
    rect(x, y, w, h, gray = 0.95) { this.ops.push(`${gray} g ${x} ${y} ${w} ${h} re f 0 g`); }

    /** Load a JPEG once; returns false (and the document simply omits it) if it cannot be fetched. */
    async loadImage(key, src) {
        if (!src?.jpg) return false;
        try {
            this.images[key] = { bin: await fetchJpeg(src.jpg), w: src.width, h: src.height, name: `Im${Object.keys(this.images).length + 1}` };
            return true;
        } catch { return false; }
    }
    /** Draw a loaded image fitted into a box (keeps aspect ratio), anchored bottom-left at x,y. */
    image(key, x, y, maxW, maxH) {
        const im = this.images[key];
        if (!im) return 0;
        const s = Math.min(maxW / im.w, maxH / im.h);
        const w = im.w * s; const h = im.h * s;
        this.ops.push(`q ${w.toFixed(2)} 0 0 ${h.toFixed(2)} ${x.toFixed(2)} ${y.toFixed(2)} cm /${im.name} Do Q`);
        return w;
    }

    /** Diagonal status watermark drawn under the content of every page. */
    watermark(text) { this.mark = text ? String(text).toUpperCase() : null; }

    /** Logo + company block on the left, document title and meta on the right. Call after useBrand(). */
    letterhead(title, subtitle, meta = []) {
        const top = this.y;
        let x = this.margin;
        const lw = this.image('logo', x, top - 38, 120, 42);
        if (lw) x += lw + 10;
        const b = brand ?? {};
        this.text(x, top - 10, b.company || 'Auxtech', { size: 13, bold: true });
        const contact = [b.address, b.phone, b.email, (b.website || '').replace(/^https?:\/\//, '')].filter(Boolean);
        contact.slice(0, 3).forEach((c, i) => this.text(x, top - 23 - i * 10, c, { size: 8, gray: 0.4 }));
        this.text(this.right, top - 10, title, { size: title.length > 24 ? 11 : title.length > 16 ? 13 : 16, bold: true, align: 'right' });
        if (subtitle) this.text(this.right, top - 24, subtitle, { size: 9, align: 'right', gray: 0.3 });
        meta.forEach((m, i) => this.text(this.right, top - 38 - i * 11, `${m.label}: ${m.value}`, { size: 8.5, align: 'right' }));
        this.y = top - Math.max(52, 44 + meta.length * 11);
        this.line(this.margin, this.y, this.right, this.y, 0.75);
        this.y -= 18;
    }

    /** Loads logo + seal (+ optional client signature) for this document. */
    async useBrand({ client = null } = {}) {
        await Promise.all([this.loadImage('logo', brand?.logo), this.loadImage('seal', brand?.seal), client ? this.loadImage('client', client) : null]);
        return this;
    }

    /** Signature row: accounts seal (always) and the client's signature (client documents only). */
    signatures({ sealLabel = 'Authorised signature', clientLabel = null } = {}) {
        this.ensure(95);
        const y = this.y - 60;
        const colW = 170;
        this.image('seal', this.margin, y + 4, colW, 52);
        this.line(this.margin, y, this.margin + colW, y, 0.5);
        this.text(this.margin, y - 11, sealLabel, { size: 8, gray: 0.35 });
        if (brand?.company) this.text(this.margin, y - 21, `${brand.signature_title ? brand.signature_title + ', ' : ''}${brand.company}`, { size: 8, gray: 0.35 });
        if (clientLabel) {
            const x = this.right - colW;
            this.image('client', x, y + 4, colW, 52);
            this.line(x, y, this.right, y, 0.5);
            this.text(x, y - 11, clientLabel, { size: 8, gray: 0.35 });
        }
        this.y = y - 32;
    }

    heading(s, sub) {
        this.text(this.margin, this.y, s, { size: 16, bold: true }); this.y -= 18;
        if (sub) { this.text(this.margin, this.y, sub, { size: 9 }); this.y -= 14; }
        this.y -= 6;
    }

    /** columns: [{label, width, align}] widths sum ≤ printable width */
    table(columns, rows, { bold = () => false, shade = () => false } = {}) {
        const x0 = this.margin;
        const head = () => {
            this.ensure(20); this.rect(x0, this.y - 5, A4[0] - 2 * this.margin, 16, 0.93);
            let x = x0 + 4;
            columns.forEach((c) => { this.text(c.align === 'right' ? x + c.width - 8 : x, this.y, c.label, { size: 8, bold: true, align: c.align }); x += c.width; });
            this.y -= 18;
        };
        head();
        rows.forEach((r, i) => {
            if (this.y - 14 < this.margin) { this.newPage(); head(); }
            if (shade(r, i)) this.rect(x0, this.y - 4, A4[0] - 2 * this.margin, 14, 0.97);
            let x = x0 + 4;
            columns.forEach((c, j) => { this.text(c.align === 'right' ? x + c.width - 8 : x, this.y, r[j], { size: 9, bold: bold(r, i), align: c.align }); x += c.width; });
            this.y -= 14;
        });
        this.y -= 8;
    }

    /** Label / value rows right-aligned (invoice totals). */
    totals(rows) {
        rows.forEach(([label, value, strong]) => {
            this.ensure(16);
            if (strong) this.rect(this.right - 220, this.y - 5, 220, 17, 0.93);
            this.text(this.right - 212, this.y, label, { size: 9.5, bold: !!strong });
            this.text(this.right - 6, this.y, value, { size: 9.5, bold: !!strong, align: 'right' });
            this.y -= 17;
        });
        this.y -= 6;
    }

    watermarkOps() {
        if (!this.mark) return [];
        const size = this.mark.length > 6 ? 64 : 96;
        const c = Math.cos(Math.PI / 5); const s = Math.sin(Math.PI / 5);
        const w = width(this.mark, size) * 1.08; // bold is a little wider than the estimate
        const x = A4[0] / 2 - (w / 2) * c; const y = A4[1] / 2 - (w / 2) * s;
        const rgb = WATERMARK_TONES[this.mark] ?? '0.9 0.9 0.9';
        return [`q /GS1 gs ${rgb} rg BT /F2 ${size} Tf ${c.toFixed(4)} ${s.toFixed(4)} ${(-s).toFixed(4)} ${c.toFixed(4)} ${x.toFixed(2)} ${y.toFixed(2)} Tm (${esc(this.mark)}) Tj ET Q`];
    }

    build() {
        const objs = [];
        const add = (s) => objs.push(s) && objs.length;
        add('<< /Type /Catalog /Pages 2 0 R >>');
        add('PAGES');
        add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        const xobjects = Object.values(this.images).map((im) => {
            const id = add(`<< /Type /XObject /Subtype /Image /Width ${im.w} /Height ${im.h} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${im.bin.length} >>\nstream\n${im.bin}\nendstream`);
            return `/${im.name} ${id} 0 R`;
        });
        const gs = add('<< /Type /ExtGState /ca 0.16 /CA 0.16 >>');
        const res = `<< /Font << /F1 3 0 R /F2 4 0 R >> /ExtGState << /GS1 ${gs} 0 R >>${xobjects.length ? ` /XObject << ${xobjects.join(' ')} >>` : ''} >>`;
        const kids = [];
        this.pages.forEach((ops, i) => {
            const footer = `0.45 g BT /F1 7.5 Tf ${this.margin} 22 Td (${esc(`${brand?.company ? brand.company + ' - ' : ''}${this.title}`)} - page ${i + 1} of ${this.pages.length}) Tj ET 0 g`;
            const stream = [...ops, footer, ...this.watermarkOps()].join('\n');
            const c = add(`<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`);
            kids.push(add(`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${A4[0]} ${A4[1]}] /Resources ${res} /Contents ${c} 0 R >>`));
        });
        objs[1] = `<< /Type /Pages /Kids [${kids.map((k) => `${k} 0 R`).join(' ')}] /Count ${kids.length} >>`;
        let out = '%PDF-1.4\n%\xE2\xE3\xCF\xD3\n';
        const offsets = [];
        objs.forEach((o, i) => { offsets.push(out.length); out += `${i + 1} 0 obj\n${o}\nendobj\n`; });
        const xref = out.length;
        out += `xref\n0 ${objs.length + 1}\n0000000000 65535 f \n${offsets.map((o) => `${String(o).padStart(10, '0')} 00000 n \n`).join('')}`;
        out += `trailer\n<< /Size ${objs.length + 1} /Root 1 0 R /Info << /Title (${esc(this.title)}) /Producer (Auxtech Ops) >> >>\nstartxref\n${xref}\n%%EOF`;
        // one char = one byte (images are binary strings; all text is escaped to ASCII)
        const bytes = new Uint8Array(out.length);
        for (let i = 0; i < out.length; i++) bytes[i] = out.charCodeAt(i) & 0xff;
        return new Blob([bytes], { type: 'application/pdf' });
    }

    download(filename) {
        const url = URL.createObjectURL(this.build());
        const a = Object.assign(document.createElement('a'), { href: url, download: filename });
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 2000);
    }
}

/** Opens the browser print dialog for the current page (print CSS hides app chrome). */
export function printPage(title) {
    const prev = document.title;
    if (title) document.title = title; // becomes the default PDF file name
    window.print();
    setTimeout(() => (document.title = prev), 500);
}
