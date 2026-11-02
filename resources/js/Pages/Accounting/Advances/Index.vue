<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, monthLabel, todayIso } from '@/lib/format';

const props = defineProps({ advances: Object, outstanding: [Object, Array], employees: Array });
const { can } = usePermissions();
const open = ref(false);
const employee = ref(null);
const form = useForm({ txn_date: todayIso(), employee_id: '', type: 'Disbursement', amount: '', related_salary_period: '', notes: '' });
const fetcher = async (q) => props.employees.filter((e) => e.label?.toLowerCase().includes((q ?? '').toLowerCase()));
const submit = () => form.transform((d) => ({ ...d, employee_id: employee.value?.value, related_salary_period: d.related_salary_period ? `${d.related_salary_period}-01` : null }))
    .post('/accounting/advances', { preserveScroll: true, onSuccess: () => { open.value = false; form.reset(); } });
</script>

<template>
    <Head title="Employee advances" />
    <PageHeader title="Employee advances" subtitle="Disbursements are cash out and an asset; recoveries are non-cash salary deductions linked to a payroll">
        <Button v-if="can('accounting.payroll.create')" icon="plus" class="hidden sm:inline-flex" @click="open = true">New entry</Button>
    </PageHeader>
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div v-for="(v, name) in outstanding" :key="name" class="card p-3"><p class="truncate text-xs text-ink-3">{{ name }}</p><p class="num text-lg font-semibold">{{ money(v) }}</p><p class="text-[11px] text-ink-3">outstanding</p></div>
    </div>
    <Card :padded="false">
        <DataTable :rows="advances.data" :columns="[{ key: 'employee', label: 'Employee', primary: true, format: (v, r) => r.employee?.name }, { key: 'amount', label: 'Amount', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'type', label: 'Type' }, { key: 'txn_date', label: 'Date', format: date }, { key: 'related_salary_period', label: 'Payroll month', format: monthLabel }, { key: 'code', label: 'ID' }]">
            <template #cell-type="{ value }"><Badge :tone="value === 'Recovery' ? 'positive' : 'warning'" :label="value" /></template>
        </DataTable>
        <Pagination :page="advances" />
    </Card>
    <BottomBar v-if="can('accounting.payroll.create')"><Button icon="plus" @click="open = true">New entry</Button></BottomBar>
    <Drawer v-model="open" title="Advance disbursement / recovery">
        <form id="adv-form" class="space-y-4" @submit.prevent="submit">
            <Combobox v-model="employee" label="Employee" :fetcher="fetcher" placeholder="Choose employee" :error="form.errors.employee_id" />
            <div class="grid grid-cols-2 gap-3">
                <Select v-model="form.type" label="Type" :options="['Disbursement', 'Recovery']" />
                <Input v-model="form.txn_date" type="date" label="Transaction date" :max="todayIso()" :error="form.errors.txn_date" />
            </div>
            <MoneyInput v-model="form.amount" label="Amount" required :error="form.errors.amount" />
            <Input v-if="form.type === 'Recovery'" v-model="form.related_salary_period" type="month" label="Payroll month deducted" hint="Defaults to the transaction month" />
            <Textarea v-model="form.notes" label="Notes" :rows="2" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="adv-form" :loading="form.processing">Save</Button></template>
    </Drawer>
</template>
