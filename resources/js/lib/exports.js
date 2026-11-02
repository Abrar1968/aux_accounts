// One-click PDF exports built with the in-house writer (lib/pdf.js).
import { PdfDoc } from './pdf';
import { money } from './format';

const plain = (v) => money(v, { decimals: true }).replace('৳', 'BDT ');

export function exportStatementPdf(label, lines) {
    const doc = new PdfDoc({ title: `Auxtech statements ${label}` });
    doc.heading(`Financial statements - ${label}`, 'Accrual basis - Auxtech Ops');
    const groups = [['Income statement', ['income', 'expense', 'result']], ['Cash reconciliation', ['cash']], ['Balance sheet', ['asset', 'liability', 'equity', 'check']]];
    const totals = /^(total_|net_|ending_cash|balance_check)/;
    for (const [name, sections] of groups) {
        doc.ensure(40); doc.text(40, doc.y, name, { size: 11, bold: true }); doc.y -= 16;
        const rows = lines.filter((l) => sections.includes(l.section));
        doc.table([{ label: 'Line', width: 360 }, { label: 'BDT', width: 155, align: 'right' }], rows.map((l) => [l.label, plain(l.amount)]),
            { bold: (_, i) => totals.test(rows[i].key), shade: (_, i) => totals.test(rows[i].key) });
    }
    doc.download(`auxtech-statement-${label}.pdf`);
}

export function exportTablePdf(title, columns, rows, filename) {
    const doc = new PdfDoc({ title });
    doc.heading(title, `Exported ${new Date().toLocaleString('en-GB', { timeZone: 'Asia/Dhaka' })}`);
    doc.table(columns, rows.map((r) => r.map((v) => String(v ?? '').replace('৳', 'BDT '))));
    doc.download(filename);
}
