<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import RowActions from '@/Components/ui/RowActions.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useCrud } from '@/Composables/useCrud';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import { useFilters } from '@/Composables/useFilters';
import { usePermissions } from '@/Composables/usePermissions';
import { dateTime, money, monthIso, monthLabel } from '@/lib/format';

const props = defineProps({ services: Object, filters: [Object, Array] });
const { filters } = useFilters({ q: props.filters?.q ?? '' });
const { can } = usePermissions();
const columns = [
    { key: 'client', label: 'Client', primary: true, avatar: true, sub: (r) => r.code },
    { key: 'status', label: 'Status', secondary: true },
    { key: 'service_month', label: 'Service month', format: monthLabel },
    { key: 'revenue', label: 'Earned', align: 'right', format: (v) => money(v) },
    { key: 'cash_received', label: 'Received', align: 'right', format: (v) => money(v) },
    { key: 'accounts_receivable', label: 'A/R', align: 'right', format: (v) => money(v) },
    { key: 'payment_date', label: 'Last payment', format: dateTime },
];
const crud = useCrud({
    base: '/accounting/marketing',
    blank: { service_month: monthIso(), client: '', revenue: '', notes: '' },
    toForm: (r) => ({ ...r, service_month: String(r.service_month).slice(0, 7), notes: r.notes ?? '' }),
});
const { open, editing, form } = crud;
const submit = () => { form.transform((d) => ({ ...d, service_month: `${d.service_month}-01` })); crud.submit(); };
</script>

<template>
    <Head title="Marketing services" />
    <PageHeader title="Marketing services" subtitle="Recurring service revenue, earned in the service month (cash arrives via Payments)">
        <Button v-if="can('accounting.projects.create')" icon="plus" class="hidden sm:inline-flex" @click="crud.create()">New service</Button>
    </PageHeader>
    <Card :padded="false">
        <FilterBar v-model:q="filters.q" placeholder="Search client or code…" />
        <DataTable :columns="columns" :rows="services.data">
            <template #cell-status="{ value }"><Badge :label="value" /></template>
            <template v-if="can('accounting.projects.update') || can('accounting.projects.delete')" #actions="{ row }">
                <RowActions :edit="can('accounting.projects.update')" :remove="can('accounting.projects.delete')" @edit="crud.edit(row)" @remove="crud.askDelete(row)" />
            </template>
        </DataTable>
        <Pagination :page="services" />
    </Card>
    <BottomBar v-if="can('accounting.projects.create')"><Button icon="plus" @click="crud.create()">New service</Button></BottomBar>
    <Drawer v-model="open" :title="editing ? `Edit ${editing.code}` : 'New marketing service'" subtitle="Revenue is recognised in the service month" :icon="editing ? 'pencil' : 'megaphone'">
        <form id="mkt-form" class="space-y-4" @submit.prevent="submit">
            <Input v-model="form.service_month" type="month" label="Service month" required :error="form.errors.service_month" />
            <Input v-model="form.client" label="Client" required :error="form.errors.client" />
            <MoneyInput v-model="form.revenue" label="Revenue earned" required :error="form.errors.revenue" />
            <Textarea v-model="form.notes" label="Notes" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="mkt-form" :loading="form.processing">{{ editing ? 'Save changes' : 'Save' }}</Button></template>
    </Drawer>
    <ConfirmDialog v-model="crud.confirm.open" danger reason :loading="crud.confirm.loading" title="Delete service?" confirm-label="Delete service"
        :message="`${crud.confirm.row?.code} for ${crud.confirm.row?.client} will be removed. Services with receipts cannot be deleted.`" @confirm="crud.doDelete" />
</template>
