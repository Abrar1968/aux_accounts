<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import MonthPicker from '@/Components/ui/MonthPicker.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import { exportTablePdf } from '@/lib/exports';
import { date, money, monthLabel } from '@/lib/format';

const props = defineProps({ month: String, balances: Array, aging: Array });
const month = computed({ get: () => props.month, set: (v) => v && router.get('/accounting/receivables', { month: v }, { preserveScroll: true }) });
const tab = ref('balances');
const sum = (rows, k) => rows.reduce((s, r) => s + Number(r[k]), 0);
const buckets = computed(() => ['0-30', '31-60', '61-90', '90+'].map((b) => ({ b, total: sum(props.aging.filter((a) => a.bucket === b), 'ar') })));
const cols = [
    { key: 'name', label: 'Customer', primary: true },
    { key: 'ar', label: 'A/R', align: 'right', secondary: true, format: (v) => money(v) },
    { key: 'kind', label: 'Type' },
    { key: 'opening', label: 'Opening', align: 'right', format: (v) => money(v) },
    { key: 'earned', label: 'Earned', align: 'right', format: (v) => money(v) },
    { key: 'settled', label: 'Settled', align: 'right', format: (v) => money(v) },
    { key: 'advance', label: 'Advance', align: 'right', format: (v) => money(v) },
];
const agingCols = [
    { key: 'name', label: 'Customer', primary: true },
    { key: 'ar', label: 'Open A/R', align: 'right', secondary: true, format: (v) => money(v) },
    { key: 'reference_date', label: 'Last activity', format: date },
    { key: 'days', label: 'Days', align: 'right' },
    { key: 'bucket', label: 'Bucket' },
];
const exportPdf = () => exportTablePdf(`Receivables ${monthLabel(props.month)}`,
    [{ label: 'Customer', width: 200 }, { label: 'Code', width: 140 }, { label: 'A/R', width: 90, align: 'right' }, { label: 'Advance', width: 85, align: 'right' }],
    props.balances.map((r) => [r.name, r.code, money(r.ar), money(r.advance)]), `receivables-${props.month}.pdf`);
</script>

<template>
    <Head title="Receivables" />
    <PageHeader title="Receivables" :subtitle="`Balances at the end of ${monthLabel(month)}`">
        <MonthPicker v-model="month" />
        <Button variant="secondary" size="sm" icon="download" @click="exportPdf">PDF</Button>
    </PageHeader>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Accounts receivable" :value="money(sum(balances, 'ar'))" />
        <StatCard label="Customer advances" :value="money(sum(balances, 'advance'))" />
        <StatCard label="Over 90 days" :value="money(buckets[3].total)" tone="negative" />
        <StatCard label="Open accounts" :value="String(balances.filter((b) => b.ar > 0).length)" />
    </div>
    <div class="mt-5 mb-3"><Tabs v-model="tab" :tabs="[{ key: 'balances', label: 'Month-end balances', count: balances.length }, { key: 'aging', label: 'Aging', count: aging.length }]" /></div>
    <Card v-if="tab === 'balances'" :padded="false">
        <DataTable :columns="cols" :rows="balances" row-key="code">
            <template #cell-name="{ row }"><Link :href="`/accounting/receivables/${row.type}/${row.id}/statement`" class="block truncate font-medium hover:underline">{{ row.name }}</Link><span class="block truncate text-xs text-ink-3">{{ row.code }}</span></template>
        </DataTable>
    </Card>
    <template v-else>
        <div class="mb-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="b in buckets" :key="b.b" class="card p-3"><p class="text-xs text-ink-3">{{ b.b }} days</p><p class="num text-lg font-semibold">{{ money(b.total) }}</p></div>
        </div>
        <Card :padded="false">
            <DataTable :columns="agingCols" :rows="aging" row-key="code">
                <template #cell-bucket="{ value }"><Badge :tone="value === '90+' ? 'negative' : value === '0-30' ? 'positive' : 'warning'" :label="value" /></template>
            </DataTable>
        </Card>
    </template>
</template>
