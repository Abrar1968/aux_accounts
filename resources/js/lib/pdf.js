// Minimal PDF 1.4 writer (no third-party code). Used for one-click tabular exports;
// rich documents use the print views + "Save as PDF". Helvetica has no ৳ glyph → "BDT".
const A4 = [595.28, 841.89];

function esc(s) {
    return String(s ?? '')
        .replace(/৳/g, 'BDT ')
        .replace(/[–—]/g, '-')
        .replace(/[^\x20-\x7E]/g, '?')
        .replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
}

/** Rough Helvetica width (pt) — enough for right-aligning numbers. */
const width = (s, size) => String(s).length * size * 0.5;

export class PdfDoc {
    constructor({ title = 'Report', margin = 40 } = {}) {
        this.title = title; this.margin = margin; this.pages = []; this.newPage();
    }
    newPage() { this.ops = []; this.pages.push(this.ops); this.y = A4[1] - this.margin; }
    ensure(h) { if (this.y - h < this.margin) this.newPage(); }
    text(x, y, s, { size = 10, bold = false, align = 'left' } = {}) {
        const tx = align === 'right' ? x - width(s, size) : x;
        this.ops.push(`BT /${bold ? 'F2' : 'F1'} ${size} Tf ${tx.toFixed(2)} ${y.toFixed(2)} Td (${esc(s)}) Tj ET`);
    }
    line(x1, y1, x2, y2, gray = 0.85) { this.ops.push(`${gray} G 0.6 w ${x1} ${y1} m ${x2} ${y2} l S 0 G`); }
    rect(x, y, w, h, gray = 0.95) { this.ops.push(`${gray} g ${x} ${y} ${w} ${h} re f 0 g`); }

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

    build() {
        const objs = [];
        const add = (s) => objs.push(s) && objs.length;
        add('<< /Type /Catalog /Pages 2 0 R >>');
        add('PAGES');
        add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        const kids = [];
        this.pages.forEach((ops, i) => {
            const footer = `BT /F1 8 Tf ${this.margin} 20 Td (${esc(this.title)} - page ${i + 1} of ${this.pages.length}) Tj ET`;
            const stream = [...ops, footer].join('\n');
            const c = add(`<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`);
            kids.push(add(`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${A4[0]} ${A4[1]}] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ${c} 0 R >>`));
        });
        objs[1] = `<< /Type /Pages /Kids [${kids.map((k) => `${k} 0 R`).join(' ')}] /Count ${kids.length} >>`;
        let out = '%PDF-1.4\n';
        const offsets = [];
        objs.forEach((o, i) => { offsets.push(out.length); out += `${i + 1} 0 obj\n${o}\nendobj\n`; });
        const xref = out.length;
        out += `xref\n0 ${objs.length + 1}\n0000000000 65535 f \n${offsets.map((o) => `${String(o).padStart(10, '0')} 00000 n \n`).join('')}`;
        out += `trailer\n<< /Size ${objs.length + 1} /Root 1 0 R /Info << /Title (${esc(this.title)}) /Producer (Auxtech Ops) >> >>\nstartxref\n${xref}\n%%EOF`;
        return new Blob([out], { type: 'application/pdf' });
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
