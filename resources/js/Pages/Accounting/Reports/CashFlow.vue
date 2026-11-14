<script setup>
// Statement of cash flows (IAS 7): direct method by activity, the indirect-method reconciliation
// from net profit, non-cash transactions and a month-by-month view. Self-checks must all be 0.00.
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import PrintLayout from '@/Components/print/PrintLayout.vue';
import ReportRange from '@/Components/reports/ReportRange.vue';
import ReportChecks from '@/Components/reports/ReportChecks.vue';
import { exportReportPdf } from '@/lib/exports';
import { printPage } from '@/lib/pdf';
import { date, money } from '@/lib/format';

const props = defineProps({ report: Object, presets: Array });
const r = computed(() => props.report);
const sec = (k) => r.value.sections.find((x) => x.key === k);
const m = (v) => money(v, { decimals: true });
const visit = (f, t) => router.get('/accounting/reports/cash-flow', { from: f, to: t }, { preserveScroll: true });

function pdf() {
    const cols = [{ label: 'Item', width: 375 }, { label: 'BDT', width: 140, align: 'right' }];
    const direct = [];
    for (const s of r.value.sections) {
        direct.push({ cells: [s.label.toUpperCase(), ''], bold: true });
        s.lines.forEach((l) => direct.push({ cells: [`   ${l.label}`, m(l.amount)] }));
        direct.push({ cells: [`Net cash from ${s.label.toLowerCase()}`, m(s.total)], bold: true, shade: true });
    }
    direct.push({ cells: ['Net increase / (decrease) in cash', m(r.value.net_change)], bold: true });
    direct.push({ cells: ['Cash at the beginning of the period', m(r.value.opening_cash)] });
    direct.push({ cells: ['Cash at the end of the period', m(r.value.closing_cash)], bold: true, shade: true });
    const indirect = [...r.value.indirect.lines.map((l) => ({ cells: [l.label, m(l.amount)] })), { cells: ['Net cash from operating activities', m(r.value.indirect.total)], bold: true, shade: true }];
    const blocks = [{ columns: cols, rows: direct }, { heading: 'Reconciliation of net profit to operating cash flow (indirect method)', columns: cols, rows: indirect }];
    if (r.value.non_cash.length) blocks.push({ heading: 'Non-cash transactions (not in the cash flow)', columns: [{ label: 'Date', width: 80 }, { label: 'Transaction', width: 295 }, { label: 'BDT', width: 140, align: 'right' }], rows: r.value.non_cash.map((n) => ({ cells: [date(n.date), n.label, m(n.amount)] })) });
    exportReportPdf({ title: 'Cash flow statement', subtitle: `${r.value.label} · direct method (IAS 7)`, filename: `auxtech-cash-flow-${r.value.from}-${r.value.to}.pdf`,
        meta: [{ label: 'Closing cash', value: m(r.value.closing_cash) }], blocks, note: 'Opening cash + net change = closing cash; indirect operating cash flow = direct (difference 0.00).' });
}
</script>

<template>
    <Head title="Cash flow statement" />
    <PageHeader title="Cash flow statement" :subtitle="`${r.label} · where the cash came from and where it went (direct method, IAS 7)`">
        <Button variant="secondary" size="sm" icon="download" @click="pdf">PDF</Button>
        <Button size="sm" icon="printer" @click="printPage(`Auxtech cash flow ${r.label}`)">Print</Button>
    </PageHeader>
    <ReportRange :presets="presets" :from="r.from" :to="r.to" @change="visit" />

    <div class="no-print mb-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <StatCard icon="wallet" label="Opening cash" :value="m(r.opening_cash)" :tone="r.opening_cash < 0 ? 'negative' : 'neutral'" hint="Start of the period" />
        <StatCard icon="briefcase" label="Operating" :value="m(sec('operating').total)" :tone="sec('operating').total < 0 ? 'negative' : 'positive'" hint="Running the business" />
        <StatCard icon="box" label="Investing" :value="m(sec('investing').total)" :tone="sec('investing').total < 0 ? 'negative' : 'neutral'" hint="Fixed assets" />
        <StatCard icon="scale" label="Financing" :value="m(sec('financing').total)" :tone="sec('financing').total < 0 ? 'negative' : 'positive'" hint="Owners & lenders" />
        <StatCard variant="hero" icon="cash" label="Closing cash" :value="m(r.closing_cash)" :hint="`Net change ${m(r.net_change)}`" />
    </div>

    <PrintLayout title="Cash flow statement" :subtitle="`${r.label} · direct method`" :meta="[{ label: 'Opening cash', value: m(r.opening_cash) }, { label: 'Closing cash', value: m(r.closing_cash) }]">
        <div class="grid items-start gap-10 lg:grid-cols-2 print:grid-cols-1">
            <section>
                <table class="w-full text-sm">
                    <tbody>
                        <template v-for="s in r.sections" :key="s.key">
                            <tr><th colspan="2" class="pb-1 pt-5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-3 first:pt-0">{{ s.label }}</th></tr>
                            <tr v-for="l in s.lines" :key="l.label" class="break-inside-avoid"><td class="py-1.5 pl-4 text-ink-2">{{ l.label }}</td><td :class="l.amount < 0 && 'text-negative'" class="num py-1.5 text-right">{{ m(l.amount) }}</td></tr>
                            <tr class="border-t border-line font-semibold"><td class="py-2">Net cash from {{ s.label.toLowerCase() }}</td><td :class="s.total < 0 && 'text-negative'" class="num py-2 text-right">{{ m(s.total) }}</td></tr>
                        </template>
                        <tr class="border-t-2 border-line-strong font-bold"><td class="pt-4 pb-1.5">Net increase / (decrease) in cash</td><td :class="r.net_change < 0 && 'text-negative'" class="num pt-4 pb-1.5 text-right">{{ m(r.net_change) }}</td></tr>
                        <tr><td class="py-1.5 text-ink-2">Cash at the beginning of the period</td><td class="num py-1.5 text-right">{{ m(r.opening_cash) }}</td></tr>
                        <tr class="border-y-2 border-ink text-[15px] font-bold"><td class="py-2">Cash at the end of the period</td><td :class="r.closing_cash < 0 && 'text-negative'" class="num py-2 text-right">{{ m(r.closing_cash) }}</td></tr>
                    </tbody>
                </table>
                <p class="mt-2 text-[11px] text-ink-3">A negative cash figure is a bank overdraft.</p>
            </section>
            <section>
                <h2 class="mb-2 text-sm font-semibold">Reconciliation of net profit to operating cash flow</h2>
                <p class="mb-3 text-xs text-ink-3">Indirect method: net profit adjusted for non-cash items and working-capital movements. It must equal the direct figure.</p>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="l in r.indirect.lines" :key="l.label" class="break-inside-avoid"><td class="py-1.5 pr-3 text-ink-2">{{ l.label }}</td><td :class="l.amount < 0 && 'text-negative'" class="num py-1.5 text-right">{{ m(l.amount) }}</td></tr>
                        <tr class="border-t border-line font-semibold"><td class="py-2">Net cash from operating activities</td><td :class="r.indirect.total < 0 && 'text-negative'" class="num py-2 text-right">{{ m(r.indirect.total) }}</td></tr>
                    </tbody>
                </table>
                <template v-if="r.non_cash.length">
                    <h2 class="mb-2 mt-8 text-sm font-semibold">Non-cash transactions</h2>
                    <p class="mb-3 text-xs text-ink-3">Recorded in the books but no cash moved, so they are disclosed here instead of in the cash flow.</p>
                    <table class="w-full text-sm"><tbody>
                        <tr v-for="(n, i) in r.non_cash" :key="i" class="break-inside-avoid"><td class="w-24 py-1.5 align-top text-ink-3">{{ date(n.date) }}</td><td class="py-1.5 pr-3 text-ink-2">{{ n.label }}</td><td class="num py-1.5 text-right align-top">{{ m(n.amount) }}</td></tr>
                    </tbody></table>
                </template>
            </section>
        </div>

        <h2 class="mb-2 mt-10 text-sm font-semibold break-after-avoid">By month</h2>
        <div class="overflow-x-auto">
            <table class="w-full min-w-max text-sm">
                <thead><tr class="border-b border-line text-[11px] uppercase tracking-wider text-ink-3"><th class="py-2 text-left font-semibold">Month</th><th class="py-2 text-right font-semibold">Operating</th><th class="py-2 text-right font-semibold">Investing</th><th class="py-2 text-right font-semibold">Financing</th><th class="py-2 text-right font-semibold">Net change</th></tr></thead>
                <tbody>
                    <tr v-for="x in r.months" :key="x.month" class="border-b border-line/60"><td class="py-1.5">{{ x.label }}</td>
                        <td v-for="k in ['operating', 'investing', 'financing', 'net']" :key="k" :class="[x[k] < 0 && 'text-negative', k === 'net' && 'font-semibold']" class="num py-1.5 pl-4 text-right">{{ money(x[k]) }}</td></tr>
                </tbody>
            </table>
        </div>
        <ReportChecks :checks="r.checks" />
    </PrintLayout>
</template>
