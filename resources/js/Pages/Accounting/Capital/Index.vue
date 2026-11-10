<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Donut from '@/Components/charts/Donut.vue';
import Badge from '@/Components/ui/Badge.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import RowActions from '@/Components/ui/RowActions.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { useCrud } from '@/Composables/useCrud';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, pct, todayIso } from '@/lib/format';

const props = defineProps({ cap_table: Array, transactions: Object });
const { can } = usePermissions();
const owners = computed(() => props.cap_table.filter((c) => c.is_equity && c.ownership > 0).map((c) => ({ label: c.name, amount: c.ownership })));
const paidIn = computed(() => props.cap_table.reduce((s, c) => s + c.paid_in, 0));
const committed = computed(() => props.cap_table.reduce((s, c) => s + Number(c.commitment || 0), 0));
const investors = computed(() => props.cap_table.map((c) => ({ value: c.id, label: c.name })));
const TYPES = ['Capital contribution', 'Distribution', 'Share transfer / reclassification', 'Opening paid-in capital'];

const crud = useCrud({
    base: '/accounting/capital',
    blank: { txn_date: todayIso(), investor_id: '', type: 'Capital contribution', cash_impact: '', equity_impact: '', counterparty: '', ownership_impact: '', notes: '' },
    toForm: (r) => ({ ...r, txn_date: r.txn_date?.slice(0, 10), counterparty: r.counterparty ?? '', ownership_impact: r.ownership_impact ?? '', notes: r.notes ?? '' }),
});
const { open, editing, form } = crud;
const canEdit = computed(() => can('accounting.ledger.update') || can('accounting.ledger.delete'));
</script>

<template>
    <Head title="Capital" />
    <PageHeader title="Capital & ownership" subtitle="Cap table today and the capital ledger">
        <Button v-if="can('accounting.ledger.create')" icon="plus" @click="crud.create()">New capital entry</Button>
    </PageHeader>
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-3">
        <StatCard variant="hero" icon="pie" label="Paid-in capital" :value="money(paidIn)" hint="Equity contributions to date" />
        <StatCard icon="target" label="Committed" :value="money(committed)" hint="Investor commitments" />
        <StatCard icon="users" label="Investors" :value="String(cap_table.length)" :hint="`${owners.length} with equity`" />
    </div>
    <div class="grid gap-4 lg:grid-cols-3">
        <Card title="Ownership" icon="pie"><Donut :items="owners" :center="money(paidIn, { compact: true })" center-label="paid-in" /></Card>
        <Card title="Cap table" :padded="false" class="lg:col-span-2">
            <DataTable :rows="cap_table" :columns="[{ key: 'name', label: 'Investor', primary: true, avatar: true, sub: (r) => r.role }, { key: 'ownership', label: 'Ownership', align: 'right', secondary: true, format: (v) => pct(v, 1) }, { key: 'is_equity', label: 'Class', format: (v) => (v ? 'Equity' : 'Not equity') }, { key: 'commitment', label: 'Commitment', align: 'right', format: (v) => money(v) }, { key: 'paid_in', label: 'Paid-in', align: 'right', format: (v) => money(v) }]">
                <template #cell-is_equity="{ value }"><Badge :tone="value ? 'brand' : 'warning'" :label="value ? 'Equity' : 'Not equity'" /></template>
            </DataTable>
        </Card>
    </div>
    <Card title="Capital ledger" subtitle="Contributions, distributions and reclassifications" :padded="false" class="mt-4">
        <DataTable :rows="transactions.data" :columns="[{ key: 'investor', label: 'Investor', primary: true, avatar: true, format: (v) => v?.name, sub: (r) => r.type }, { key: 'equity_impact', label: 'Equity', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'txn_date', label: 'Date', format: date }, { key: 'cash_impact', label: 'Cash', align: 'right', format: (v) => money(v) }, { key: 'notes', label: 'Notes', hideMobile: true, class: 'max-w-[16rem] truncate' }]">
            <template v-if="canEdit" #actions="{ row }"><RowActions :edit="can('accounting.ledger.update')" :remove="can('accounting.ledger.delete')" @edit="crud.edit(row)" @remove="crud.askDelete(row)" /></template>
        </DataTable>
        <Pagination :page="transactions" />
    </Card>

    <Drawer v-model="open" :title="editing ? 'Edit capital entry' : 'New capital entry'" subtitle="Cash impact drives the cash statement; equity impact drives paid-in capital" :icon="editing ? 'pencil' : 'pie'">
        <form id="cap-form" class="space-y-4" @submit.prevent="crud.submit()">
            <Select v-model="form.investor_id" label="Investor" placeholder="Choose investor" :options="investors" :error="form.errors.investor_id" />
            <div class="grid grid-cols-2 gap-3">
                <Input v-model="form.txn_date" type="date" label="Date" :max="todayIso()" :error="form.errors.txn_date" />
                <Select v-model="form.type" label="Type" :options="TYPES" :error="form.errors.type" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Input v-model="form.cash_impact" type="number" step="0.01" prefix="৳" label="Cash impact" hint="+ in / − out" :error="form.errors.cash_impact" />
                <Input v-model="form.equity_impact" type="number" step="0.01" prefix="৳" label="Equity impact" :error="form.errors.equity_impact" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Input v-model="form.counterparty" label="Counterparty" />
                <Input v-model="form.ownership_impact" label="Ownership impact" placeholder="e.g. +5%" />
            </div>
            <Textarea v-model="form.notes" label="Notes" :rows="2" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="cap-form" :loading="form.processing">{{ editing ? 'Save changes' : 'Save entry' }}</Button></template>
    </Drawer>
    <ConfirmDialog v-model="crud.confirm.open" danger reason :loading="crud.confirm.loading" title="Delete capital entry?" confirm-label="Delete entry"
        :message="`${crud.confirm.row?.type} of ${money(crud.confirm.row?.equity_impact)} for ${crud.confirm.row?.investor?.name} on ${date(crud.confirm.row?.txn_date)} will be removed.`" @confirm="crud.doDelete" />
</template>
