<script setup>
// Trial balance at a month end: every balance-sheet account plus the income and expense
// accounts of the financial year (or since the start). Debits must equal credits.
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import MonthPicker from '@/Components/ui/MonthPicker.vue';
import Switch from '@/Components/ui/Switch.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Badge from '@/Components/ui/Badge.vue';
import PrintLayout from '@/Components/print/PrintLayout.vue';
import ReportChecks from '@/Components/reports/ReportChecks.vue';
import { exportReportPdf } from '@/lib/exports';
import { printPage } from '@/lib/pdf';
import { money } from '@/lib/format';

const props = defineProps({ report: Object, fiscalLabel: String, mode: String });
const r = computed(() => props.report);
const visit = (extra) => router.get('/accounting/reports/trial-balance', { month: r.value.as_at, from: props.mode === 'start' ? 'start' : undefined, ...extra }, { preserveScroll: true });
const month = computed({ get: () => r.value.as_at, set: (v) => v && visit({ month: v }) });
const mode = computed({ get: () => props.mode, set: (v) => visit({ from: v === 'start' ? 'start' : undefined }) });
const hideZero = ref(true);
const rows = computed(() => r.value.rows.filter((x) => !hideZero.value || x.debit || x.credit));
const groups = computed(() => {
    const order = ['Asset', 'Asset (contra)', 'Liability', 'Equity', 'Income', 'Expense'];
    const names = { Asset: 'Assets', 'Asset (contra)': 'Assets', Liability: 'Liabilities', Equity: 'Equity', Income: 'Income', Expense: 'Expenses' };
    const out = [];
    for (const t of order) for (const x of rows.value.filter((y) => y.type === t)) {
        const name = names[t];
        if (!out.length || out.at(-1).name !== name) out.push({ name, rows: [] });
        out.at(-1).rows.push(x);
    }
    return out;
});
const diff = computed(() => Math.round((r.value.total_debit - r.value.total_credit) * 100) / 100);
const m = (v) => (v ? money(v, { decimals: true }) : '');

function pdf() {
    const body = [];
    for (const g of groups.value) {
        body.push({ cells: ['', g.name.toUpperCase(), '', ''], bold: true });
        g.rows.forEach((x) => body.push({ cells: [x.code, x.name, m(x.debit), m(x.credit)] }));
    }
    body.push({ cells: ['', 'TOTAL', money(r.value.total_debit, { decimals: true }), money(r.value.total_credit, { decimals: true })], bold: true, shade: true });
    exportReportPdf({ title: 'Trial balance', subtitle: `As at ${r.value.as_at_label} · income & expenses ${r.value.period_label}`, filename: `auxtech-trial-balance-${r.value.as_at}.pdf`,
        meta: [{ label: 'Difference', value: diff.value === 0 ? 'Balanced (0.00)' : money(diff.value, { decimals: true }) }],
        blocks: [{ columns: [{ label: 'Code', width: 50 }, { label: 'Account', width: 245 }, { label: 'Debit (BDT)', width: 110, align: 'right' }, { label: 'Credit (BDT)', width: 110, align: 'right' }], rows: body }],
        note: 'Debits equal credits; the income and expense accounts equal the profit & loss for the same period.' });
}
</script>

<template>
    <Head title="Trial balance" />
    <PageHeader title="Trial balance" :subtitle="`As at ${r.as_at_label} · every account's balance — total debits must equal total credits`">
        <MonthPicker v-model="month" />
        <Button variant="secondary" size="sm" icon="download" @click="pdf">PDF</Button>
        <Button size="sm" icon="printer" @click="printPage(`Auxtech trial balance ${r.as_at}`)">Print</Button>
    </PageHeader>
    <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
        <Tabs v-model="mode" :tabs="[{ key: 'fy', label: `${fiscalLabel} income & expenses` }, { key: 'start', label: 'Since the start' }]" />
        <div class="flex items-center gap-3">
            <Badge :tone="diff === 0 ? 'positive' : 'negative'" size="lg">{{ diff === 0 ? 'Balanced · difference 0.00' : `Out of balance by ${money(diff, { decimals: true })}` }}</Badge>
            <Switch v-model="hideZero" label="Hide zero balances" />
        </div>
    </div>

    <PrintLayout title="Trial balance" :subtitle="`As at ${r.as_at_label} · income & expenses ${r.period_label}`" :meta="[{ label: 'Total debits', value: money(r.total_debit, { decimals: true }) }, { label: 'Total credits', value: money(r.total_credit, { decimals: true }) }]">
        <table class="w-full text-sm">
            <thead><tr class="border-b-2 border-line-strong text-[11px] uppercase tracking-wider text-ink-3">
                <th class="w-16 py-2 text-left font-semibold">Code</th><th class="py-2 text-left font-semibold">Account</th>
                <th class="py-2 text-right font-semibold">Debit</th><th class="py-2 text-right font-semibold">Credit</th>
            </tr></thead>
            <tbody>
                <template v-for="g in groups" :key="g.name">
                    <tr><th colspan="4" class="pb-1 pt-5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-3">{{ g.name }}</th></tr>
                    <tr v-for="x in g.rows" :key="x.code" class="break-inside-avoid border-b border-line/50">
                        <td class="num py-1.5 text-ink-3">{{ x.code }}</td><td class="py-1.5 text-ink-2">{{ x.name }}</td>
                        <td class="num py-1.5 text-right">{{ m(x.debit) }}</td><td class="num py-1.5 text-right">{{ m(x.credit) }}</td>
                    </tr>
                </template>
                <tr class="border-y-2 border-ink text-[15px] font-bold"><td /><td class="py-2">Total</td>
                    <td class="num py-2 text-right">{{ money(r.total_debit, { decimals: true }) }}</td><td class="num py-2 text-right">{{ money(r.total_credit, { decimals: true }) }}</td></tr>
            </tbody>
        </table>
        <p class="mt-2 text-[11px] text-ink-3">Balance-sheet accounts are closing balances at {{ r.as_at_label }}; income and expense accounts cover {{ r.period_label }}. Retained earnings brought forward hold the profit of earlier periods.</p>
        <ReportChecks :checks="r.checks" />
    </PrintLayout>
</template>
