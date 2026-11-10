<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import Switch from '@/Components/ui/Switch.vue';
import RowActions from '@/Components/ui/RowActions.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useCrud } from '@/Composables/useCrud';
import BottomBar from '@/Components/ui/BottomBar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { money, monthIso, monthLabel } from '@/lib/format';

const props = defineProps({ templates: Array, categories: Array, month: String });
const { can } = usePermissions();
const open = ref(false);
const editing = ref(null);
const form = useForm({ name: '', category: 'Rent', subcategory: '', amount: '', day_of_month: 1, start_month: monthIso(), end_month: '', is_active: true, notes: '' });
function start(t = null) {
    editing.value = t; form.reset(); form.clearErrors();
    if (t) Object.assign(form, { ...t, start_month: String(t.start_month).slice(0, 7), end_month: t.end_month ? String(t.end_month).slice(0, 7) : '', subcategory: t.subcategory ?? '', notes: t.notes ?? '' });
    open.value = true;
}
const del = useCrud({ base: '/accounting/recurring', blank: {}, reason: false });
const submit = () => {
    const f = form.transform((d) => ({ ...d, start_month: `${d.start_month}-01`, end_month: d.end_month ? `${d.end_month}-01` : null }));
    editing.value ? f.put(`/accounting/recurring/${editing.value.id}`, { onSuccess: () => (open.value = false) }) : f.post('/accounting/recurring', { onSuccess: () => (open.value = false) });
};
</script>

<template>
    <Head title="Recurring bills" />
    <PageHeader title="Recurring bills" subtitle="Templates create one unpaid bill per month (daily automation, or generate now)">
        <Button v-if="can('accounting.expenses.create')" size="sm" variant="secondary" @click="router.post(`/accounting/recurring/generate/${month}`, {}, { preserveScroll: true })">Generate {{ monthLabel(month) }}</Button>
        <Button v-if="can('accounting.expenses.create')" icon="plus" class="hidden sm:inline-flex" @click="start()">New template</Button>
    </PageHeader>
    <Card :padded="false">
        <DataTable :rows="templates" clickable @row="(t) => can('accounting.expenses.create') && start(t)" empty="No templates yet — add rent, internet, hosting…"
            :columns="[{ key: 'name', label: 'Template', primary: true }, { key: 'amount', label: 'Amount', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'category', label: 'Category' }, { key: 'day_of_month', label: 'Day', align: 'right' }, { key: 'start_month', label: 'From', format: monthLabel }, { key: 'end_month', label: 'Until', format: (v) => (v ? monthLabel(v) : 'Open') }, { key: 'bills_count', label: 'Bills', align: 'right' }, { key: 'is_active', label: 'Status' }]">
            <template #cell-is_active="{ value }"><Badge :tone="value ? 'positive' : 'neutral'" :label="value ? 'Active' : 'Paused'" /></template>
            <template v-if="can('accounting.expenses.create')" #actions="{ row }"><RowActions edit remove @edit="start(row)" @remove="del.askDelete(row)" /></template>
        </DataTable>
    </Card>
    <BottomBar v-if="can('accounting.expenses.create')"><Button icon="plus" @click="start()">New template</Button></BottomBar>
    <Drawer v-model="open" :icon="editing ? 'pencil' : 'reverse'" :title="editing ? editing.name : 'New recurring bill'" subtitle="The daily automation creates the bill each month">
        <form id="rec-form" class="space-y-4" @submit.prevent="submit">
            <Input v-model="form.name" label="Description" required placeholder="Office rent" :error="form.errors.name" />
            <div class="grid grid-cols-2 gap-3"><Select v-model="form.category" label="Category" :options="categories" /><Input v-model="form.subcategory" label="Subcategory" /></div>
            <div class="grid grid-cols-2 gap-3"><MoneyInput v-model="form.amount" label="Amount" required :error="form.errors.amount" /><Input v-model="form.day_of_month" type="number" min="1" max="31" label="Day of month" :error="form.errors.day_of_month" /></div>
            <div class="grid grid-cols-2 gap-3"><Input v-model="form.start_month" type="month" label="Start month" :error="form.errors.start_month" /><Input v-model="form.end_month" type="month" label="End month" hint="Blank = no end" /></div>
            <Switch v-model="form.is_active" label="Active" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="rec-form" :loading="form.processing">Save template</Button></template>
    </Drawer>
    <ConfirmDialog v-model="del.confirm.open" danger :loading="del.confirm.loading" title="Delete template?" confirm-label="Delete template"
        :message="`“${del.confirm.row?.name}” will stop generating bills. Bills already created are kept.`" @confirm="del.doDelete" />
</template>
