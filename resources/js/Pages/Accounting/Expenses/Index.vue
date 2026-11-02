<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
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
import RowActions from '@/Components/ui/RowActions.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useCrud } from '@/Composables/useCrud';
import { useFilters } from '@/Composables/useFilters';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, monthLabel, todayIso } from '@/lib/format';

const props = defineProps({ expenses: Object, categories: Array, filters: Object });
const { filters } = useFilters({ q: props.filters.q ?? '', category: props.filters.category ?? '' });
const { can } = usePermissions();
const columns = [
    { key: 'description', label: 'Bill', primary: true, sub: (r) => `${r.code} · ${date(r.expense_date)}`, format: (v, r) => v || r.subcategory },
    { key: 'amount', label: 'Amount', align: 'right', secondary: true, format: (v) => money(v) },
    { key: 'category', label: 'Category' },
    { key: 'accrual_month', label: 'Accrual month', format: monthLabel },
    { key: 'payable', label: 'Payable', align: 'right', format: (v) => money(v) },
    { key: 'payment_status', label: 'Status' },
];
const crud = useCrud({
    base: '/accounting/expenses',
    blank: { expense_date: todayIso(), accrual_period: '', category: 'Office & Admin', subcategory: '', description: '', amount: '', notes: '' },
    toForm: (r) => ({ ...r, expense_date: r.expense_date?.slice(0, 10), accrual_period: r.accrual_period?.slice(0, 10) ?? '', subcategory: r.subcategory ?? '', notes: r.notes ?? '' }),
});
const { open, editing, form } = crud;
const submit = () => crud.submit();
</script>

<template>
    <Head title="Expenses" />
    <PageHeader title="Operating expenses" subtitle="Bills are recognised in their accrual month; pay them in installments from the bill">
        <Button v-if="can('accounting.expenses.create')" icon="plus" class="hidden sm:inline-flex" @click="crud.create()">New bill</Button>
    </PageHeader>
    <Card :padded="false">
        <FilterBar v-model:q="filters.q" placeholder="Search description, code, category…">
            <select v-model="filters.category" class="max-w-[12rem] rounded-lg border border-line bg-surface px-3 text-sm" aria-label="Category">
                <option value="">All categories</option><option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
        </FilterBar>
        <DataTable :columns="columns" :rows="expenses.data" clickable @row="(r) => router.visit(`/accounting/expenses/${r.id}`)">
            <template #cell-payment_status="{ value, row }"><Badge :label="value" /><Badge v-if="row.entry_check !== 'OK'" tone="negative" class="ml-1" :label="row.entry_check" /></template>
            <template v-if="can('accounting.expenses.update') || can('accounting.expenses.delete')" #actions="{ row }">
                <RowActions :edit="can('accounting.expenses.update')" :remove="can('accounting.expenses.delete')" @edit="crud.edit(row)" @remove="crud.askDelete(row)" />
            </template>
        </DataTable>
        <Pagination :page="expenses" />
    </Card>
    <BottomBar v-if="can('accounting.expenses.create')"><Button icon="plus" @click="crud.create()">New bill</Button></BottomBar>
    <Drawer v-model="open" :title="editing ? `Edit ${editing.code}` : 'New bill'" :subtitle="editing ? 'Changes recalculate statements automatically' : 'Record the cost when incurred; pay it later'" :icon="editing ? 'pencil' : 'receipt'">
        <form id="exp-form" class="space-y-4" @submit.prevent="submit">
            <Input v-model="form.description" label="Description" required :error="form.errors.description" />
            <div class="grid grid-cols-2 gap-3">
                <Select v-model="form.category" label="Category" :options="categories" :error="form.errors.category" />
                <Input v-model="form.subcategory" label="Subcategory" />
            </div>
            <MoneyInput v-model="form.amount" label="Amount incurred" required :error="form.errors.amount" />
            <div class="grid grid-cols-2 gap-3">
                <Input v-model="form.expense_date" type="date" label="Expense date" required :error="form.errors.expense_date" />
                <Input v-model="form.accrual_period" type="date" label="Accrual period" hint="Defaults to the expense date" />
            </div>
            <Textarea v-model="form.notes" label="Notes" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="exp-form" :loading="form.processing">{{ editing ? 'Save changes' : 'Save bill' }}</Button></template>
    </Drawer>
    <ConfirmDialog v-model="crud.confirm.open" danger reason :loading="crud.confirm.loading" title="Delete bill?" confirm-label="Delete bill"
        :message="`${crud.confirm.row?.code} · ${crud.confirm.row?.description} (${money(crud.confirm.row?.amount)}) will be removed. Bills with payments must have them reversed first.`" @confirm="crud.doDelete" />
</template>
