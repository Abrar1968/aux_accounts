// One-click PDF exports built with the in-house writer (lib/pdf.js). Every document carries the
// company letterhead (logo) and the accounts seal; client documents also carry the client's
// signature; billing documents carry a PAID / PARTIALLY PAID / UNPAID watermark.
import { PdfDoc } from './pdf';
import { date, money, monthLabel } from './format';

const plain = (v) => money(v, { decimals: true }).replace('৳', 'BDT ');
const today = () => date(new Date().toISOString().slice(0, 10));

export async function exportStatementPdf(label, lines) {
    const doc = await new PdfDoc({ title: `Financial statements ${label}` }).useBrand();
    doc.letterhead('Financial statements', `${label} · accrual basis`, [{ label: 'Generated', value: today() }]);
    const groups = [['Income statement', ['income', 'expense', 'result']], ['Cash reconciliation', ['cash']], ['Balance sheet', ['asset', 'liability', 'equity', 'check']]];
    const totals = /^(total_|net_|ending_cash|balance_check)/;
    for (const [name, sections] of groups) {
        doc.ensure(40); doc.text(40, doc.y, name, { size: 11, bold: true }); doc.y -= 16;
        const rows = lines.filter((l) => sections.includes(l.section));
        doc.table([{ label: 'Line', width: 360 }, { label: 'BDT', width: 155, align: 'right' }], rows.map((l) => [l.label, plain(l.amount)]),
            { bold: (_, i) => totals.test(rows[i].key), shade: (_, i) => totals.test(rows[i].key) });
    }
    doc.signatures({ sealLabel: 'Prepared & approved by' });
    doc.download(`auxtech-statement-${label}.pdf`);
}

export async function exportTablePdf(title, columns, rows, filename, { heading = null, subtitle = null, watermark = null, client = null, clientLabel = null, meta = [] } = {}) {
    const doc = await new PdfDoc({ title }).useBrand({ client });
    doc.watermark(watermark);
    doc.letterhead(heading ?? title, subtitle, [...meta, { label: 'Generated', value: today() }]);
    doc.table(columns, rows.map((r) => r.map((v) => String(v ?? '').replace('৳', 'BDT '))));
    doc.signatures({ clientLabel: client ? clientLabel ?? 'Client signature' : null });
    doc.download(filename);
}

export async function exportInvoicePdf(inv) {
    const doc = await new PdfDoc({ title: `Invoice ${inv.number}` }).useBrand({ client: inv.client_signature });
    doc.watermark(inv.status);
    doc.letterhead('INVOICE', inv.number, [{ label: 'Issued', value: date(inv.issued) }, { label: 'Reference', value: inv.reference }, { label: 'Status', value: inv.status }]);
    doc.text(40, doc.y, 'BILL TO', { size: 8, bold: true, gray: 0.4 }); doc.y -= 13;
    doc.text(40, doc.y, inv.customer, { size: 11, bold: true }); doc.y -= 13;
    doc.text(40, doc.y, inv.title, { size: 9, gray: 0.35 }); doc.y -= 22;
    doc.table([{ label: 'Description', width: 395 }, { label: 'Amount (BDT)', width: 120, align: 'right' }],
        inv.lines.map((l) => [l.detail ? `${l.description} - ${l.detail}` : l.description, plain(l.amount)]));
    doc.totals([['Invoice total', plain(inv.total)], ['Paid to date', plain(inv.paid)], [inv.balance >= 0 ? 'Balance due' : 'Advance (credit)', plain(Math.abs(inv.balance)), true]]);
    if (inv.payments.length) {
        doc.ensure(40); doc.text(40, doc.y, 'Payments received', { size: 10, bold: true }); doc.y -= 15;
        doc.table([{ label: 'Date', width: 80 }, { label: 'Receipt', width: 130 }, { label: 'Method', width: 165 }, { label: 'Amount (BDT)', width: 140, align: 'right' }],
            inv.payments.map((p) => [date(p.date), p.code, p.method || '-', plain(p.amount)]));
    }
    doc.signatures({ clientLabel: inv.client_signature ? `Client signature - ${inv.client_signature.name}` : null });
    doc.download(`${inv.number}.pdf`);
}

export async function exportCustomerStatementPdf(s) {
    await exportTablePdf(`Statement of account - ${s.customer}`,
        [{ label: 'Date', width: 70 }, { label: 'Description', width: 215 }, { label: 'Debit', width: 75, align: 'right' }, { label: 'Credit', width: 75, align: 'right' }, { label: 'Balance', width: 80, align: 'right' }],
        [['', 'Opening balance', '', '', plain(s.opening)], ...s.lines.map((l) => [date(l.date), `${l.description} (${l.reference})`, l.debit ? plain(l.debit) : '', l.credit ? plain(l.credit) : '', plain(l.balance)]), ['', 'Balance due', plain(s.total_debit), plain(s.total_credit), plain(s.closing)]],
        `statement-${s.code}.pdf`,
        { heading: 'STATEMENT', subtitle: `${s.customer} - ${s.code}`, watermark: s.status, client: s.client_signature, clientLabel: s.client_signature ? `Client signature - ${s.client_signature.name}` : null, meta: [{ label: 'As of', value: date(s.as_of) }] });
}

export const payslipStatus = (status) => (status === 'Settled' ? 'PAID' : status === 'Partly settled' ? 'PARTIALLY PAID' : status === 'Unpaid' ? 'UNPAID' : null);

export async function exportPayslipPdf(p, payments) {
    const doc = await new PdfDoc({ title: `Payslip ${p.employee} ${monthLabel(p.period)}` }).useBrand();
    doc.watermark(payslipStatus(p.status));
    doc.letterhead('PAYSLIP', `${p.employee} · ${monthLabel(p.period)}`, [{ label: 'Payroll ID', value: p.code }, { label: 'Status', value: p.status }]);
    const rows = [['Gross salary', plain(p.gross_salary)], ['Less: advance recovery', `(${plain(p.advance_recovery)})`], ['Net payable', plain(p.gross_salary - p.advance_recovery)],
        ['Paid in cash / bank', plain(p.salary_cash_settled)], ...(p.referral_paid ? [['Referral commission paid (separate)', plain(p.referral_paid)]] : []), ['Outstanding', plain(p.unpaid)]];
    doc.table([{ label: 'Item', width: 375 }, { label: 'BDT', width: 140, align: 'right' }], rows, { bold: (r) => ['Net payable', 'Outstanding'].includes(r[0]), shade: (r) => ['Net payable', 'Outstanding'].includes(r[0]) });
    doc.ensure(40); doc.text(40, doc.y, 'Payments', { size: 10, bold: true }); doc.y -= 15;
    doc.table([{ label: 'Date', width: 120 }, { label: 'Method', width: 255 }, { label: 'Amount (BDT)', width: 140, align: 'right' }],
        payments.length ? payments.map((s) => [date(s.paid_on), s.method ?? s.origin, plain(s.amount)]) : [['', 'No payments yet', '']]);
    doc.signatures({ sealLabel: 'Authorised by', clientLabel: 'Employee signature' });
    doc.download(`payslip-${p.code}.pdf`);
}

export async function exportIntakePdf(p, sections) {
    const doc = await new PdfDoc({ title: `Project intake ${p.code}` }).useBrand({ client: p.client_signature });
    doc.letterhead('PROJECT INTAKE FORM', `${p.code} - ${p.name}`, [{ label: 'Assign date', value: date(p.start_date) }, { label: 'Delivery', value: date(p.est_delivery_date) }]);
    for (const [title, rows] of sections) {
        doc.ensure(40); doc.text(40, doc.y, title.toUpperCase(), { size: 9, bold: true, gray: 0.35 }); doc.y -= 14;
        doc.table([{ label: 'Field', width: 175 }, { label: 'Details', width: 340 }], rows.map(([k, v]) => [k, String(v || '-').replace('৳', 'BDT ')]),
            { bold: (r) => ['Contract value', 'Due'].includes(r[0]) });
    }
    if (p.notes) { doc.ensure(30); doc.text(40, doc.y, 'NOTE', { size: 9, bold: true, gray: 0.35 }); doc.y -= 14; doc.text(40, doc.y, p.notes.split('\n')[0], { size: 9 }); doc.y -= 20; }
    doc.signatures({ sealLabel: 'Authorised by', clientLabel: 'Client signature' });
    doc.download(`intake-${p.code}.pdf`);
}
