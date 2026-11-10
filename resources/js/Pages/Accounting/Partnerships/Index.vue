<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import RowActions from '@/Components/ui/RowActions.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useCrud } from '@/Composables/useCrud';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, monthLabel, pct } from '@/lib/format';

const props = defineProps({ collaborators: Array, packages: Array, assignments: Array, revenues: Object });
const d10 = (v) => (v ? String(v).slice(0, 10) : '');
const col = useCrud({
    base: '/accounting/partnerships/collaborators', reason: false,
    blank: { name: '', status: 'Draft', agreement_start: '', agreement_end: '', contact: '', notes: '' },
    toForm: (r) => ({ ...r, agreement_start: d10(r.agreement_start), agreement_end: d10(r.agreement_end), contact: r.contact ?? '', notes: r.notes ?? '' }),
});
const asg = useCrud({
    base: '/accounting/partnerships/assignments',
    blank: { collaborator_id: '', package_id: '', effective_from: '', effective_through: '', status: 'Draft', earning_method: 'Direct Amount', share_percent: '', notes: '' },
    toForm: (r) => ({ ...r, effective_from: d10(r.effective_from), effective_through: d10(r.effective_through), share_percent: r.share_percent ?? '', notes: r.notes ?? '' }),
});
const { open: colOpen, editing: colEditing, form: cf } = col;
const { open: asgOpen, editing: asgEditing, form: af } = asg;
const STATUSES = ['Draft', 'Active', 'Inactive'];
const { can } = usePermissions();
const tab = ref('revenues');
const editing = ref(null);
const form = useForm({ gross_sales: '', direct_amount: '', notes: '' });
function edit(r) {
    if (!can('accounting.projects.update')) return;
    editing.value = r;
    form.gross_sales = r.gross_sales ?? ''; form.direct_amount = r.direct_amount ?? ''; form.notes = r.notes ?? '';
}
const save = () => form.transform((d) => ({ gross_sales: d.gross_sales === '' ? null : d.gross_sales, direct_amount: d.direct_amount === '' ? null : d.direct_amount, notes: d.notes }))
    .put(`/accounting/partnerships/revenues/${editing.value.id}`, { preserveScroll: true, onSuccess: () => (editing.value = null) });
</script>

<template>
    <Head title="Partnerships" />
    <PageHeader title="Partnerships" subtitle="Collaborator packages · monthly rows are generated for active assignments; blank earned amount = not recognised">
        <Button v-if="can('accounting.projects.create')" variant="secondary" icon="user" @click="col.create(); tab = 'collaborators'">New collaborator</Button>
        <Button v-if="can('accounting.projects.create')" icon="plus" @click="asg.create(); tab = 'assignments'">New assignment</Button>
    </PageHeader>
    <div class="mb-3"><Tabs v-model="tab" :tabs="[{ key: 'revenues', label: 'Monthly revenue', count: revenues.total }, { key: 'assignments', label: 'Assignments', count: assignments.length }, { key: 'collaborators', label: 'Collaborators', count: collaborators.length }, { key: 'packages', label: 'Packages', count: packages.length }]" /></div>
    <Card v-if="tab === 'revenues'" :padded="false">
        <DataTable :rows="revenues.data" clickable @row="edit" empty="No monthly rows yet — activate an assignment with an effective date."
            :columns="[{ key: 'code', label: 'Revenue ID', primary: true }, { key: 'recognized_revenue', label: 'Recognised', align: 'right', secondary: true, format: (v) => (v === null ? 'Pending input' : money(v)) }, { key: 'earned_month', label: 'Month', format: monthLabel }, { key: 'earning_method', label: 'Method' }, { key: 'share_percent', label: 'Share', align: 'right', format: (v) => (v === null ? '—' : pct(v)) }]">
            <template #cell-code="{ row }"><span class="block font-medium">{{ row.assignment?.collaborator?.name }}</span><span class="block text-xs text-ink-3">{{ row.code }}</span></template>
        </DataTable>
        <Pagination :page="revenues" />
    </Card>
    <Card v-else-if="tab === 'assignments'" :padded="false">
        <DataTable :rows="assignments" :columns="[{ key: 'code', label: 'Assignment', primary: true, avatar: true, format: (v, r) => `${r.collaborator?.name} · ${r.package?.name}`, sub: (r) => r.code }, { key: 'status', label: 'Status', secondary: true }, { key: 'effective_from', label: 'From', format: date }, { key: 'effective_through', label: 'Through', format: date }, { key: 'earning_method', label: 'Method' }, { key: 'notes', label: 'Notes', hideMobile: true }]">
            <template #cell-status="{ value }"><Badge :label="value" /></template>
            <template v-if="can('accounting.projects.update')" #actions="{ row }"><RowActions edit :remove="can('accounting.projects.delete')" @edit="asg.edit(row)" @remove="asg.askDelete(row)" /></template>
        </DataTable>
    </Card>
    <Card v-else-if="tab === 'collaborators'" :padded="false">
        <DataTable :rows="collaborators" :columns="[{ key: 'name', label: 'Collaborator', primary: true, avatar: true, sub: (r) => r.contact || r.code }, { key: 'status', label: 'Status', secondary: true }, { key: 'code', label: 'ID' }, { key: 'agreement_start', label: 'Agreement start', format: date }, { key: 'agreement_end', label: 'End', format: date }]">
            <template #cell-status="{ value }"><Badge :label="value" /></template>
            <template v-if="can('accounting.projects.update')" #actions="{ row }"><RowActions edit :remove="can('accounting.projects.delete')" @edit="col.edit(row)" @remove="col.askDelete(row)" /></template>
        </DataTable>
    </Card>
    <Card v-else :padded="false">
        <DataTable :rows="packages" :columns="[{ key: 'name', label: 'Package', primary: true }, { key: 'code', label: 'ID', secondary: true }, { key: 'description', label: 'Description' }, { key: 'accounting_note', label: 'Accounting note', hideMobile: true }]" />
    </Card>
    <Modal :model-value="!!editing" @update:model-value="(v) => !v && (editing = null)" :title="`Earned amount · ${editing?.code}`">
        <div class="space-y-4">
            <MoneyInput v-if="editing?.earning_method === 'Share of Sales'" v-model="form.gross_sales" label="Gross package sales" :hint="`Auxtech share ${pct(editing?.share_percent)}`" />
            <MoneyInput v-else v-model="form.direct_amount" label="Direct earned amount" hint="0 is an explicit zero; leave blank if not known yet" />
        </div>
        <template #footer><Button variant="secondary" @click="editing = null">Cancel</Button><Button :loading="form.processing" @click="save">Save</Button></template>
    </Modal>
    <Drawer v-model="colOpen" :icon="colEditing ? 'pencil' : 'user'" :title="colEditing ? colEditing.name : 'New collaborator'" subtitle="Agencies and partners who sell Auxtech packages">
        <form id="col-form" class="space-y-4" @submit.prevent="col.submit()">
            <Input v-model="cf.name" label="Name" required :error="cf.errors.name" />
            <Select v-model="cf.status" label="Status" :options="STATUSES" :error="cf.errors.status" />
            <div class="grid grid-cols-2 gap-3"><Input v-model="cf.agreement_start" type="date" label="Agreement start" :error="cf.errors.agreement_start" /><Input v-model="cf.agreement_end" type="date" label="Agreement end" :error="cf.errors.agreement_end" /></div>
            <Input v-model="cf.contact" label="Contact" placeholder="Phone or email" />
            <Textarea v-model="cf.notes" label="Notes" :rows="2" />
        </form>
        <template #footer><Button variant="secondary" @click="colOpen = false">Cancel</Button><Button type="submit" form="col-form" :loading="cf.processing">{{ colEditing ? 'Save changes' : 'Add collaborator' }}</Button></template>
    </Drawer>
    <Drawer v-model="asgOpen" :icon="asgEditing ? 'pencil' : 'link'" :title="asgEditing ? `Edit ${asgEditing.code}` : 'New assignment'" subtitle="Active assignments create a monthly revenue row automatically">
        <form id="asg-form" class="space-y-4" @submit.prevent="asg.submit()">
            <Select v-model="af.collaborator_id" label="Collaborator" placeholder="Choose collaborator" :options="props.collaborators.map((c) => ({ value: c.id, label: c.name }))" :error="af.errors.collaborator_id" />
            <Select v-model="af.package_id" label="Package" placeholder="Choose package" :options="props.packages.map((p) => ({ value: p.id, label: p.name }))" :error="af.errors.package_id" />
            <div class="grid grid-cols-2 gap-3"><Select v-model="af.status" label="Status" :options="STATUSES" /><Select v-model="af.earning_method" label="Earning method" :options="['Direct Amount', 'Share of Sales']" /></div>
            <Input v-if="af.earning_method === 'Share of Sales'" v-model="af.share_percent" type="number" step="0.01" min="0" max="1" label="Auxtech share (0–1)" placeholder="e.g. 0.3" :error="af.errors.share_percent" />
            <div class="grid grid-cols-2 gap-3"><Input v-model="af.effective_from" type="date" label="Effective from" :error="af.errors.effective_from" /><Input v-model="af.effective_through" type="date" label="Effective through" :error="af.errors.effective_through" /></div>
            <Textarea v-model="af.notes" label="Notes" :rows="2" />
        </form>
        <template #footer><Button variant="secondary" @click="asgOpen = false">Cancel</Button><Button type="submit" form="asg-form" :loading="af.processing">{{ asgEditing ? 'Save changes' : 'Create assignment' }}</Button></template>
    </Drawer>
    <ConfirmDialog v-model="col.confirm.open" danger :loading="col.confirm.loading" title="Delete collaborator?" confirm-label="Delete"
        :message="`${col.confirm.row?.name} will be removed. Collaborators with assignments must be set inactive instead.`" @confirm="col.doDelete" />
    <ConfirmDialog v-model="asg.confirm.open" danger reason :loading="asg.confirm.loading" title="Delete assignment?" confirm-label="Delete assignment"
        :message="`${asg.confirm.row?.code} and its empty monthly rows will be removed. Assignments with earned revenue or receipts must be set inactive.`" @confirm="asg.doDelete" />
</template>
