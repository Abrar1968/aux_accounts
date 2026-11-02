<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import ReceivablePicker from '@/Components/accounting/ReceivablePicker.vue';
import { useFilters } from '@/Composables/useFilters';
import { usePermissions } from '@/Composables/usePermissions';
import { newRequestId } from '@/Composables/useRequestId';
import { dateTime, money, nowLocalIso } from '@/lib/format';

const props = defineProps({ payments: Object, filters: Object });
const { filters } = useFilters({ q: props.filters.q ?? '', type: props.filters.type ?? '' });
const { can } = usePermissions();
const columns = [
    { key: 'canonical_name', label: 'Customer', primary: true },
    { key: 'cash_received', label: 'Cash', align: 'right', secondary: true, format: (v) => money(v) },
    { key: 'paid_at', label: 'Date', format: dateTime },
    { key: 'revenue_type', label: 'Type' },
    { key: 'processing_fee', label: 'Fee', align: 'right', format: (v) => money(v) },
    { key: 'method', label: 'Method', format: (v, r) => [v, r.account].filter(Boolean).join(' · ') || '—' },
];
const open = ref(false);
const receivable = ref(null);
const form = useForm({ paid_at: '', receivable_type: '', receivable_id: '', cash_received: '', processing_fee: '', method: 'Bank Transfer', account: 'DBBL', notes: '', request_id: '' });
function start() { form.reset(); form.clearErrors(); receivable.value = null; form.paid_at = nowLocalIso(); form.request_id = newRequestId(); open.value = true; }
const submit = () => form.transform((d) => ({ ...d, receivable_type: receivable.value?.type, receivable_id: receivable.value?.id }))
    .post('/accounting/payments', { preserveScroll: true, onSuccess: () => (open.value = false) });
</script>

<template>
    <Head title="Customer payments" />
    <PageHeader title="Customer payments" subtitle="Real cash receipts only — processing fees are expensed automatically">
        <Button v-if="can('accounting.payments.create')" icon="plus" class="hidden sm:inline-flex" @click="start">Record receipt</Button>
    </PageHeader>
    <Card :padded="false">
        <FilterBar v-model:q="filters.q" placeholder="Search customer, code or revenue ID…">
            <select v-model="filters.type" class="rounded-lg border border-line bg-surface px-3 text-sm" aria-label="Type">
                <option value="">All sources</option><option value="project">Projects</option><option value="marketing">Marketing</option><option value="opening">Legacy A/R</option><option value="partnership">Partnerships</option>
            </select>
        </FilterBar>
        <DataTable :columns="columns" :rows="payments.data">
            <template #cell-canonical_name="{ row }"><span class="block truncate font-medium">{{ row.canonical_name }}</span><span class="block truncate text-xs text-ink-3">{{ row.code }} · {{ row.revenue_code }}</span></template>
            <template #cell-revenue_type="{ value }"><Badge tone="neutral" :label="value" /></template>
        </DataTable>
        <Pagination :page="payments" />
    </Card>
    <BottomBar v-if="can('accounting.payments.create')"><Button icon="plus" @click="start">Record receipt</Button></BottomBar>

    <Drawer v-model="open" title="Record customer receipt" subtitle="Safe to retry: this form carries a unique request ID">
        <form id="receipt-form" class="space-y-4" @submit.prevent="submit">
            <ReceivablePicker v-model="receivable" :error="form.errors.receivable_type || form.errors.receivable_id" />
            <Input v-model="form.paid_at" type="datetime-local" label="Receipt date & time" required :error="form.errors.paid_at" />
            <div class="grid grid-cols-2 gap-3">
                <MoneyInput v-model="form.cash_received" label="Cash received" required :error="form.errors.cash_received" />
                <MoneyInput v-model="form.processing_fee" label="Processing fee" hint="Withheld by bank/bKash" :error="form.errors.processing_fee" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Select v-model="form.method" label="Method" :options="['Bank Transfer', 'Cash', 'Bkash', 'Nagad', 'Card', 'Cheque']" />
                <Input v-model="form.account" label="Account" />
            </div>
            <Textarea v-model="form.notes" label="Notes" :rows="2" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="receipt-form" :loading="form.processing">Save receipt</Button></template>
    </Drawer>
</template>
