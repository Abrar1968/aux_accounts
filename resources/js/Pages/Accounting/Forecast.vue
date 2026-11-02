<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import LineChart from '@/Components/charts/LineChart.vue';
import { date, money, pct, shortDate } from '@/lib/format';

const props = defineProps({ forecast: Object });
const f = computed(() => props.forecast);
</script>

<template>
    <Head title="Cash forecast" />
    <PageHeader title="90-day cash-flow forecast" :subtitle="`${date(f.today)} → ${date(f.until)} · weekly`" />
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Book cash today" :value="money(f.opening)" :tone="f.opening < 0 ? 'negative' : 'neutral'" />
        <StatCard label="Projected in 90 days" :value="money(f.closing)" :tone="f.closing < 0 ? 'negative' : 'positive'" />
        <StatCard label="Lowest point" :value="money(f.lowest.balance)" :hint="f.lowest.week ? `Week ${f.lowest.week}` : 'Today'" tone="warning" />
        <StatCard label="Monthly payroll" :value="money(f.monthly_payroll)" hint="Active employees' base salary" />
    </div>
    <Card title="Projected closing balance" class="mt-4">
        <LineChart area :labels="f.weeks.map((w) => shortDate(w.from))" :series="[{ name: 'Closing balance', values: f.weeks.map((w) => w.closing) }]" title="Projected cash" />
    </Card>
    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <Card title="By week" :padded="false" class="xl:col-span-2">
            <DataTable dense :rows="f.weeks" row-key="week" :columns="[{ key: 'from', label: 'Week', primary: true, format: (v, r) => `W${r.week} · ${shortDate(r.from)} – ${shortDate(r.to)}` }, { key: 'closing', label: 'Closing', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'inflow', label: 'In', align: 'right', format: (v) => money(v) }, { key: 'outflow', label: 'Out', align: 'right', format: (v) => money(v) }]" />
        </Card>
        <Card title="Assumptions">
            <ul class="list-disc space-y-2 pl-4 text-sm text-ink-2">
                <li>Open receivables are collected at {{ pct(f.collection_rate) }}, spread evenly over the next four weeks (change in Settings).</li>
                <li>Unpaid bills and unpaid salary are paid in week 1.</li>
                <li>Payroll for active employees is paid on each month end.</li>
                <li>Recurring expense templates are paid on their day of month.</li>
                <li>New sales and Siam funding are not assumed.</li>
            </ul>
        </Card>
    </div>
</template>
