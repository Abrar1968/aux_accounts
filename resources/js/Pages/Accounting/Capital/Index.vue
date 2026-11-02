<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Donut from '@/Components/charts/Donut.vue';
import Badge from '@/Components/ui/Badge.vue';
import { date, money, pct } from '@/lib/format';

const props = defineProps({ cap_table: Array, transactions: Object });
const owners = computed(() => props.cap_table.filter((c) => c.is_equity && c.ownership > 0).map((c) => ({ label: c.name, amount: c.ownership })));
const paidIn = computed(() => props.cap_table.reduce((s, c) => s + c.paid_in, 0));
</script>

<template>
    <Head title="Capital" />
    <PageHeader title="Capital & ownership" subtitle="Cap table today and the capital ledger" />
    <div class="grid gap-4 lg:grid-cols-3">
        <Card title="Ownership"><Donut :items="owners" :center="money(paidIn, { compact: true })" center-label="paid-in" /></Card>
        <Card title="Cap table" :padded="false" class="lg:col-span-2">
            <DataTable :rows="cap_table" :columns="[{ key: 'name', label: 'Investor', primary: true }, { key: 'ownership', label: 'Ownership', align: 'right', secondary: true, format: (v) => pct(v, 1) }, { key: 'role', label: 'Role' }, { key: 'commitment', label: 'Commitment', align: 'right', format: (v) => money(v) }, { key: 'paid_in', label: 'Paid-in', align: 'right', format: (v) => money(v) }]">
                <template #cell-role="{ row }"><span class="truncate">{{ row.role }}</span> <Badge v-if="!row.is_equity" tone="warning">Not equity</Badge></template>
            </DataTable>
        </Card>
    </div>
    <Card title="Capital ledger" :padded="false" class="mt-4">
        <DataTable :rows="transactions.data" :columns="[{ key: 'investor', label: 'Investor', primary: true, format: (v) => v?.name }, { key: 'equity_impact', label: 'Equity', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'txn_date', label: 'Date', format: date }, { key: 'type', label: 'Type' }, { key: 'cash_impact', label: 'Cash', align: 'right', format: (v) => money(v) }, { key: 'notes', label: 'Notes', hideMobile: true }]" />
    </Card>
</template>
