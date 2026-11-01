<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
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
import { useFilters } from '@/Composables/useFilters';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, monthLabel, todayIso } from '@/lib/format';

const props = defineProps({ expenses: Object, categories: Array, filters: Object });
const { filters } = useFilters({ q: props.filters.q ?? '', category: props.filters.category ?? '' });
const { can } = usePermissions();
const columns = [
    { key: 'description', label: 'Bill', primary: true },
    { key: 'amount', label: 'Amount', align: 'right', secondary: true, format: (v) => money(v) },
    { key: 'category', label: 'Category' },
    { key: 'accrual_month', label: 'Accrual month', format: monthLabel },
    { key: 'payable', label: 'Payable', align: 'right', format: (v) => money(v) },
    { key: 'payment_status', label: 'Status' },
];
const open = ref(false);
const form = useForm({ expense_date: todayIso(), accrual_period: '', category: 'Office & Admin', subcategory: '', description: '', amount: '', notes: '' });
const submit = () => form.post('/accounting/expenses', { preserveScroll: true, onSuccess: () => { open.value = false; form.reset(); } });
</script>

<template>
    <Head title="Expenses" />
    <PageHeader title="Operating expenses" subtitle="Bills are recognised in their accrual month; pay them in installments from the bill">
        <Button v-if="can('accounting.expenses.create')" icon="plus" class="hidden sm:inline-flex" @click="open = true">New bill</Button>
    </PageHeader>
    <Card :padded="false">
        <FilterBar v-model:q="filters.q" placeholder="Search description, code, category…">
            <select v-model="filters.category" class="max-w-[12rem] rounded-lg border border-line bg-surface px-3 text-sm" aria-label="Category">
                <option value="">All categories</option><option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
        </FilterBar>
        <DataTable :columns="columns" :rows="expenses.data" clickable @row="(r) => router.visit(`/accounting/expenses/${r.id}`)">
            <template #cell-description="{ row }"><span class="block truncate font-medium">{{ row.description || row.subcategory }}</span><span class="block truncate text-xs text-ink-3">{{ row.code }} · {{ date(row.expense_date) }}</span></template>
            <template #cell-payment_status="{ value, row }"><Badge :label="value" /><Badge v-if="row.entry_check !== 'OK'" tone="negative" class="ml-1" :label="row.entry_check" /></template>
        </DataTable>
        <Pagination :page="expenses" />
    </Card>
    <BottomBar v-if="can('accounting.expenses.create')"><Button icon="plus" @click="open = true">New bill</Button></BottomBar>
    <Drawer v-model="open" title="New bill" subtitle="Record the cost when incurred; pay it later">
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
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="exp-form" :loading="form.processing">Save bill</Button></template>
    </Drawer>
</template>
