<script setup>
import { onMounted, ref } from 'vue';
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
import RowActions from '@/Components/ui/RowActions.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useCrud } from '@/Composables/useCrud';
import { useFilters } from '@/Composables/useFilters';
import { usePermissions } from '@/Composables/usePermissions';
import { newRequestId } from '@/Composables/useRequestId';
import { dateTime, money, nowLocalIso } from '@/lib/format';

const props = defineProps({ payments: Object, filters: Object });
const { filters } = useFilters({ q: props.filters.q ?? '', type: props.filters.type ?? '' });
const { can } = usePermissions();
const columns = [
    { key: 'canonical_name', label: 'Customer', primary: true, avatar: true, sub: (r) => `${r.code} · ${r.revenue_code}` },
    { key: 'cash_received', label: 'Cash', align: 'right', secondary: true, format: (v) => money(v) },
    { key: 'paid_at', label: 'Date', format: dateTime },
    { key: 'revenue_type', label: 'Type' },
    { key: 'processing_fee', label: 'Fee', align: 'right', format: (v) => money(v) },
    { key: 'method', label: 'Method', format: (v, r) => [v, r.account].filter(Boolean).join(' · ') || '—' },
];
const open = ref(false);
const editing = ref(null);
const receivable = ref(null);
const del = useCrud({ base: '/accounting/payments', blank: {} });
const form = useForm({ paid_at: '', receivable_type: '', receivable_id: '', cash_received: '', processing_fee: '', method: 'Bank Transfer', account: 'DBBL', notes: '', request_id: '' });
function start() { editing.value = null; form.reset(); form.clearErrors(); receivable.value = null; form.paid_at = nowLocalIso(); form.request_id = newRequestId(); open.value = true; }
function startEdit(r) {
    editing.value = r; form.clearErrors();
    Object.assign(form, { paid_at: String(r.paid_at).slice(0, 16), cash_received: r.cash_received, processing_fee: r.processing_fee, method: r.method ?? '', account: r.account ?? '', notes: r.notes ?? '', request_id: '' });
    receivable.value = { type: r.receivable_type, id: r.receivable_id, value: `${r.receivable_type}:${r.receivable_id}`, label: r.canonical_name };
    open.value = true;
}
const submit = () => {
    const req = form.transform((d) => ({ ...d, receivable_type: receivable.value?.type, receivable_id: receivable.value?.id }));
    const o = { preserveScroll: true, onSuccess: () => (open.value = false) };
    editing.value ? req.put(`/accounting/payments/${editing.value.id}`, o) : req.post('/accounting/payments', o);
};
onMounted(() => { if (new URLSearchParams(window.location.search).get('new') === '1') start(); });
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
            <template #cell-revenue_type="{ value }"><Badge tone="brand" :label="value" /></template>
            <template v-if="can('accounting.payments.update') || can('accounting.payments.delete')" #actions="{ row }">
                <RowActions :edit="can('accounting.payments.update')" :remove="can('accounting.payments.delete')" @edit="startEdit(row)" @remove="del.askDelete(row)" />
            </template>
        </DataTable>
        <Pagination :page="payments" />
    </Card>
    <BottomBar v-if="can('accounting.payments.create')"><Button icon="plus" @click="start">Record receipt</Button></BottomBar>

    <Drawer v-model="open" :title="editing ? `Edit ${editing.code}` : 'Record customer receipt'" :subtitle="editing ? 'Corrections are audited; closed periods stay locked' : 'Safe to retry: this form carries a unique request ID'" :icon="editing ? 'pencil' : 'inbox'">
        <form id="receipt-form" class="space-y-4" @submit.prevent="submit">
            <ReceivablePicker v-model="receivable" :display="editing?.canonical_name" :error="form.errors.receivable_type || form.errors.receivable_id" />
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
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="receipt-form" :loading="form.processing">{{ editing ? 'Save changes' : 'Save receipt' }}</Button></template>
    </Drawer>
    <ConfirmDialog v-model="del.confirm.open" danger reason :loading="del.confirm.loading" title="Delete receipt?" confirm-label="Delete receipt"
        :message="`${del.confirm.row?.code} (${money(del.confirm.row?.cash_received)} from ${del.confirm.row?.canonical_name}) will be removed and the receivable re-opened.`" @confirm="del.doDelete" />
</template>
