<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Switch from '@/Components/ui/Switch.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { money } from '@/lib/format';

const props = defineProps({ employees: Object, advances_outstanding: [Object, Array] });
const { can } = usePermissions();
const open = ref(false);
const editing = ref(null);
const form = useForm({ name: '', designation: '', base_salary: '', phone: '', bank_account: '', is_active: true });
function start(row = null) {
    editing.value = row;
    form.reset(); form.clearErrors();
    if (row) Object.assign(form, { name: row.name, designation: row.designation ?? '', base_salary: row.base_salary ?? '', phone: row.phone ?? '', bank_account: row.bank_account ?? '', is_active: row.is_active });
    open.value = true;
}
const submit = () => (editing.value ? form.put(`/accounting/employees/${editing.value.id}`, { onSuccess: () => (open.value = false) }) : form.post('/accounting/employees', { onSuccess: () => (open.value = false) }));
</script>

<template>
    <Head title="Employees" />
    <PageHeader title="Employees" subtitle="Payroll IDs are built from the employee name — renaming is blocked once payroll exists">
        <Button v-if="can('accounting.payroll.create')" icon="plus" class="hidden sm:inline-flex" @click="start()">Add employee</Button>
    </PageHeader>
    <Card :padded="false">
        <DataTable :rows="employees.data" clickable @row="(r) => can('accounting.payroll.update') && start(r)"
            :columns="[{ key: 'name', label: 'Name', primary: true }, { key: 'is_active', label: 'Status', secondary: true }, { key: 'designation', label: 'Designation' }, { key: 'base_salary', label: 'Base salary', align: 'right', format: (v) => money(v) }, { key: 'advance', label: 'Advance outstanding', align: 'right', format: (v, r) => money(advances_outstanding?.[r.name] ?? 0) }]">
            <template #cell-is_active="{ value }"><Badge :tone="value ? 'positive' : 'neutral'" :label="value ? 'Active' : 'Inactive'" /></template>
        </DataTable>
    </Card>
    <BottomBar v-if="can('accounting.payroll.create')"><Button icon="plus" @click="start()">Add employee</Button></BottomBar>
    <Drawer v-model="open" :title="editing ? editing.name : 'Add employee'">
        <form id="emp-form" class="space-y-4" @submit.prevent="submit">
            <Input v-model="form.name" label="Name" required :disabled="!!editing" :error="form.errors.name" />
            <Input v-model="form.designation" label="Designation" />
            <MoneyInput v-model="form.base_salary" label="Base monthly salary" hint="Used to draft each month's payroll" />
            <div class="grid grid-cols-2 gap-3"><Input v-model="form.phone" label="Phone" type="tel" /><Input v-model="form.bank_account" label="Bank account" /></div>
            <Switch v-if="editing" v-model="form.is_active" label="Active" description="Inactive employees are not drafted into payroll" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="emp-form" :loading="form.processing">Save</Button></template>
    </Drawer>
</template>
