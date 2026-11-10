<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import RowActions from '@/Components/ui/RowActions.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useCrud } from '@/Composables/useCrud';
import BottomBar from '@/Components/ui/BottomBar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, todayIso } from '@/lib/format';

const props = defineProps({ ledger: Array, siam: Object, types: Object });
const { can } = usePermissions();
const crud = useCrud({
    base: '/accounting/liabilities',
    blank: { txn_date: todayIso(), liability_code: 'LIA-SIAM-SHARE', description: '', increase: 0, decrease: '', cash_impact: '', counterparty: '', notes: '' },
    toForm: (r) => ({ ...r, txn_date: r.txn_date?.slice(0, 10), counterparty: r.counterparty ?? '', notes: r.notes ?? '' }),
});
const { open, editing, form } = crud;
const codes = Object.entries(props.types).map(([value, label]) => ({ value, label: `${value} · ${label}` }));
const submit = () => crud.submit();
</script>

<template>
    <Head title="Liabilities" />
    <PageHeader title="Liabilities" subtitle="Prior dues, asset financing, Siam deferred contract and revenue-share payments">
        <Button v-if="can('accounting.ledger.create')" icon="plus" class="hidden sm:inline-flex" @click="crud.create()">New entry</Button>
    </PageHeader>
    <Card title="Siam revenue-share contract" subtitle="15% of eligible international revenue for 24 months from Nov-2026" class="mb-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <StatCard label="Commitment" :value="money(siam.commitment)" />
            <StatCard label="Funding received" :value="money(siam.funding_received)" hint="Deferred contract liability" />
            <StatCard label="Eligible revenue" :value="money(siam.eligible_revenue)" />
            <StatCard label="Share accrued" :value="money(siam.share_accrued)" />
            <StatCard label="Share paid" :value="money(siam.share_paid)" />
        </div>
    </Card>
    <Card :padded="false">
        <DataTable :rows="ledger" :columns="[{ key: 'description', label: 'Entry', primary: true, sub: (r) => [r.liability_code, r.counterparty].filter(Boolean).join(' · ') }, { key: 'balance', label: 'Balance', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'txn_date', label: 'Date', format: date }, { key: 'liability_code', label: 'Liability' }, { key: 'increase', label: 'Increase', align: 'right', format: (v) => money(v) }, { key: 'decrease', label: 'Decrease', align: 'right', format: (v) => money(v) }, { key: 'cash_impact', label: 'Cash', align: 'right', format: (v) => money(v) }]">
            <template v-if="can('accounting.ledger.update') || can('accounting.ledger.delete')" #actions="{ row }">
                <RowActions :edit="can('accounting.ledger.update')" :remove="can('accounting.ledger.delete')" @edit="crud.edit(row)" @remove="crud.askDelete(row)" />
            </template>
        </DataTable>
    </Card>
    <BottomBar v-if="can('accounting.ledger.create')"><Button icon="plus" @click="crud.create()">New entry</Button></BottomBar>
    <Drawer v-model="open" :icon="editing ? 'pencil' : 'scale'" :title="editing ? 'Edit liability entry' : 'Liability entry'" subtitle="For a Siam share payment: decrease = amount, cash impact = −amount">
        <form id="lia-form" class="space-y-4" @submit.prevent="submit">
            <Select v-model="form.liability_code" label="Liability" :options="codes" />
            <Input v-model="form.description" label="Description" required :error="form.errors.description" />
            <Input v-model="form.txn_date" type="date" label="Date" :max="todayIso()" :error="form.errors.txn_date" />
            <div class="grid grid-cols-2 gap-3"><MoneyInput v-model="form.increase" label="Increase" /><MoneyInput v-model="form.decrease" label="Decrease / payment" /></div>
            <Input v-model="form.cash_impact" type="number" step="0.01" label="Cash impact (৳, negative = paid out)" required :error="form.errors.cash_impact" />
            <Input v-model="form.counterparty" label="Counterparty" />
            <Textarea v-model="form.notes" label="Notes" :rows="2" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="lia-form" :loading="form.processing">{{ editing ? 'Save changes' : 'Save' }}</Button></template>
    </Drawer>
    <ConfirmDialog v-model="crud.confirm.open" danger reason :loading="crud.confirm.loading" title="Delete liability entry?" confirm-label="Delete entry"
        :message="`“${crud.confirm.row?.description}” (${crud.confirm.row?.liability_code}, ${date(crud.confirm.row?.txn_date)}) will be removed and balances recalculated.`" @confirm="crud.doDelete" />
</template>
