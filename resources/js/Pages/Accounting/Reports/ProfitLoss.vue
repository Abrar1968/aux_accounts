<script setup>
// Profit & loss for any month range, with comparison and a by-month view. Built from the monthly
// statements; the self-checks at the bottom prove it ties back to them.
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import PrintLayout from '@/Components/print/PrintLayout.vue';
import ReportRange from '@/Components/reports/ReportRange.vue';
import ReportChecks from '@/Components/reports/ReportChecks.vue';
import { exportReportPdf } from '@/lib/exports';
import { printPage } from '@/lib/pdf';
import { money, pct } from '@/lib/format';

const props = defineProps({ report: Object, presets: Array, compareMode: String, view: String });
const r = computed(() => props.report);
const s = computed(() => r.value.summary);
const c = computed(() => r.value.compare_summary);
const visit = (extra) => router.get('/accounting/reports/profit-loss', { from: r.value.from, to: r.value.to, compare: props.compareMode, view: props.view, ...extra }, { preserveScroll: true });
const view = computed({ get: () => props.view, set: (v) => visit({ view: v }) });
const compare = computed({ get: () => props.compareMode, set: (v) => visit({ compare: v }) });

const m = (v) => money(v, { decimals: true });
const change = (a, b) => (b === null || b === undefined ? null : a - b);
const changePct = (a, b) => (b ? (a - b) / Math.abs(b) : null);
const share = (v) => (s.value.revenue ? v / s.value.revenue : null);
const delta = (a, b) => { const p = changePct(a, b); return p === null ? undefined : Math.round(p * 100); };

// rows for the summary table (also used for PDF)
const rows = computed(() => {
    const out = [];
    for (const g of r.value.groups) {
        if (g.key === 'depreciation') continue;
        out.push({ type: 'head', label: g.label });
        for (const l of g.lines) out.push({ type: 'line', label: l.label, amount: l.amount, compare: l.compare });
        out.push({ type: 'total', label: g.key === 'revenue' ? 'Total revenue' : `Total ${g.label.toLowerCase()}`, amount: g.total, compare: g.compare });
    }
    out.push({ type: 'key', label: 'Total operating expenses', amount: s.value.operating_expenses, compare: c.value?.operating_expenses });
    out.push({ type: 'key', label: 'Operating profit before depreciation (EBITDA)', amount: s.value.ebitda, compare: c.value?.ebitda });
    out.push({ type: 'line', label: 'Depreciation', amount: s.value.depreciation, compare: c.value?.depreciation });
    out.push({ type: 'grand', label: 'Net profit / (loss)', amount: s.value.net_profit, compare: c.value?.net_profit });
    return out;
});

// by-month view
const monthLines = computed(() => [
    ['project_revenue', 'Project revenue'], ['marketing_revenue', 'Marketing revenue'], ['partnership_revenue', 'Partnership revenue'], ['total_revenue', 'Total revenue', true],
    ['salary_expense', 'Salary'], ['bonuses', 'Bonuses'], ['referral_paid', 'Referral commissions'], ['marketing_advertising', 'Marketing & advertising'],
    ['rent', 'Rent'], ['software_hosting', 'Software & hosting'], ['office_admin', 'Office & admin'], ['legal_license', 'Legal & license'],
    ['bank_fees', 'Bank & transfer fees'], ['repairs_equipment', 'Repairs & equipment'], ['other_operating', 'Other operating'], ['siam_share', 'Siam revenue share'],
    ['depreciation', 'Depreciation'], ['total_expenses', 'Total expenses', true], ['net_income', 'Net profit / (loss)', true],
]);
const monthTotal = (k) => r.value.months.reduce((t, x) => t + Number(x[k] || 0), 0);

function pdf() {
    const cmp = !!r.value.compare;
    const columns = cmp
        ? [{ label: 'Line', width: 235 }, { label: r.value.label, width: 100, align: 'right' }, { label: r.value.compare.label, width: 100, align: 'right' }, { label: 'Change', width: 80, align: 'right' }]
        : [{ label: 'Line', width: 315 }, { label: 'BDT', width: 120, align: 'right' }, { label: '% of revenue', width: 80, align: 'right' }];
    const body = rows.value.map((x) => ({
        bold: x.type !== 'line', shade: ['key', 'grand'].includes(x.type),
        cells: x.type === 'head' ? [x.label.toUpperCase(), '', '', ''] : cmp
            ? [x.label, m(x.amount), m(x.compare), x.compare === null || x.compare === undefined ? '' : m(change(x.amount, x.compare))]
            : [x.type === 'line' ? `   ${x.label}` : x.label, m(x.amount), share(x.amount) === null ? '' : pct(share(x.amount), 1)],
    }));
    exportReportPdf({ title: 'Profit & loss', subtitle: `${r.value.label} · accrual basis`, filename: `auxtech-profit-loss-${r.value.from}-${r.value.to}.pdf`,
        meta: [{ label: 'Net profit', value: m(s.value.net_profit) }], blocks: [{ columns, rows: body }],
        note: 'Ties to the monthly financial statements: net profit = sum of monthly net income (difference 0.00).' });
}
</script>

<template>
    <Head title="Profit & loss" />
    <PageHeader title="Profit & loss" :subtitle="`${r.label} · accrual basis · income earned and costs incurred in the period`">
        <select v-model="compare" class="field !h-10 !w-auto pl-3 pr-8" aria-label="Compare with">
            <option value="previous">vs previous period</option><option value="year">vs same period last year</option><option value="none">No comparison</option>
        </select>
        <Button variant="secondary" size="sm" icon="download" @click="pdf">PDF</Button>
        <Button size="sm" icon="printer" @click="printPage(`Auxtech profit and loss ${r.label}`)">Print</Button>
    </PageHeader>
    <ReportRange :presets="presets" :from="r.from" :to="r.to" @change="(f, t) => visit({ from: f, to: t })" />

    <div class="no-print mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard variant="hero" icon="trend" label="Revenue" :value="m(s.revenue)" :delta="delta(s.revenue, c?.revenue)" :hint="c ? `${r.compare.label}: ${m(c.revenue)}` : 'Earned in the period'" />
        <StatCard icon="receipt" label="Operating expenses" :value="m(s.operating_expenses)" :delta="delta(s.operating_expenses, c?.operating_expenses)" hint="Before depreciation" />
        <StatCard icon="chart" label="EBITDA" :value="m(s.ebitda)" :tone="s.ebitda < 0 ? 'negative' : 'positive'" hint="Operating profit before depreciation" />
        <StatCard icon="pie" label="Net profit" :value="m(s.net_profit)" :tone="s.net_profit < 0 ? 'negative' : 'positive'" :hint="s.net_margin === null ? 'No revenue in the period' : `Net margin ${pct(s.net_margin, 1)}`" />
    </div>
    <div class="no-print mb-3"><Tabs v-model="view" :tabs="[{ key: 'summary', label: 'Statement' }, { key: 'monthly', label: 'By month' }]" /></div>

    <PrintLayout v-if="view === 'summary'" title="Profit & loss" :subtitle="`${r.label} · accrual basis`" :meta="[{ label: 'Net profit', value: m(s.net_profit) }, ...(r.compare ? [{ label: 'Compared with', value: r.compare.label }] : [])]">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-line text-[11px] uppercase tracking-wider text-ink-3">
                <th class="py-2 text-left font-semibold">Line</th><th class="py-2 text-right font-semibold">{{ r.label }}</th>
                <template v-if="r.compare"><th class="py-2 text-right font-semibold">{{ r.compare.label }}</th><th class="py-2 text-right font-semibold">Change</th></template>
                <th class="hidden py-2 text-right font-semibold sm:table-cell print:table-cell">% of rev.</th>
            </tr></thead>
            <tbody>
                <tr v-for="(x, i) in rows" :key="i" :class="{ 'border-t border-line font-semibold': x.type === 'total', 'border-t-2 border-line-strong font-bold': x.type === 'key', 'border-y-2 border-ink font-bold text-[15px]': x.type === 'grand' }" class="break-inside-avoid">
                    <th v-if="x.type === 'head'" :colspan="r.compare ? 5 : 3" class="pb-1 pt-5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-3">{{ x.label }}</th>
                    <template v-else>
                        <td :class="x.type === 'line' ? 'py-1.5 pl-4 text-ink-2' : 'py-2'">{{ x.label }}</td>
                        <td :class="x.amount < 0 && 'text-negative'" class="num py-1.5 text-right">{{ m(x.amount) }}</td>
                        <template v-if="r.compare">
                            <td class="num py-1.5 text-right text-ink-3">{{ m(x.compare) }}</td>
                            <td :class="change(x.amount, x.compare) < 0 ? 'text-negative' : change(x.amount, x.compare) > 0 ? 'text-positive' : 'text-ink-3'" class="num py-1.5 text-right">{{ x.compare === null || x.compare === undefined ? '' : m(change(x.amount, x.compare)) }}</td>
                        </template>
                        <td class="num hidden py-1.5 text-right text-ink-3 sm:table-cell print:table-cell">{{ share(x.amount) === null ? '—' : pct(share(x.amount), 1) }}</td>
                    </template>
                </tr>
            </tbody>
        </table>
        <ReportChecks :checks="r.checks" />
    </PrintLayout>

    <PrintLayout v-else title="Profit & loss by month" :subtitle="`${r.label} · accrual basis`" :signatures="false">
        <div class="overflow-x-auto">
            <table class="w-full min-w-max text-sm">
                <thead><tr class="border-b border-line text-[11px] uppercase tracking-wider text-ink-3">
                    <th class="sticky left-0 bg-white py-2 pr-4 text-left font-semibold">Line</th>
                    <th v-for="x in r.months" :key="x.month" class="px-3 py-2 text-right font-semibold">{{ x.label }}</th><th class="py-2 pl-3 text-right font-bold text-ink">Total</th>
                </tr></thead>
                <tbody>
                    <tr v-for="[k, label, strong] in monthLines" :key="k" :class="strong && 'border-t border-line font-semibold'">
                        <td :class="!strong && 'pl-3 text-ink-2'" class="sticky left-0 whitespace-nowrap bg-white py-1.5 pr-4">{{ label }}</td>
                        <td v-for="x in r.months" :key="x.month" :class="x[k] < 0 && 'text-negative'" class="num whitespace-nowrap px-3 py-1.5 text-right">{{ money(x[k]) }}</td>
                        <td :class="monthTotal(k) < 0 && 'text-negative'" class="num whitespace-nowrap py-1.5 pl-3 text-right font-semibold">{{ money(monthTotal(k)) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <ReportChecks :checks="r.checks" />
    </PrintLayout>
</template>
