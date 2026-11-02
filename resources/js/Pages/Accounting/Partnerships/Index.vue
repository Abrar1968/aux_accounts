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
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, monthLabel, pct } from '@/lib/format';

defineProps({ collaborators: Array, packages: Array, assignments: Array, revenues: Object });
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
    <PageHeader title="Partnerships" subtitle="Collaborator packages · monthly rows are generated for active assignments; blank earned amount = not recognised" />
    <div class="mb-3"><Tabs v-model="tab" :tabs="[{ key: 'revenues', label: 'Monthly revenue', count: revenues.total }, { key: 'assignments', label: 'Assignments', count: assignments.length }, { key: 'collaborators', label: 'Collaborators', count: collaborators.length }, { key: 'packages', label: 'Packages', count: packages.length }]" /></div>
    <Card v-if="tab === 'revenues'" :padded="false">
        <DataTable :rows="revenues.data" clickable @row="edit" empty="No monthly rows yet — activate an assignment with an effective date."
            :columns="[{ key: 'code', label: 'Revenue ID', primary: true }, { key: 'recognized_revenue', label: 'Recognised', align: 'right', secondary: true, format: (v) => (v === null ? 'Pending input' : money(v)) }, { key: 'earned_month', label: 'Month', format: monthLabel }, { key: 'earning_method', label: 'Method' }, { key: 'share_percent', label: 'Share', align: 'right', format: (v) => (v === null ? '—' : pct(v)) }]">
            <template #cell-code="{ row }"><span class="block font-medium">{{ row.assignment?.collaborator?.name }}</span><span class="block text-xs text-ink-3">{{ row.code }}</span></template>
        </DataTable>
        <Pagination :page="revenues" />
    </Card>
    <Card v-else-if="tab === 'assignments'" :padded="false">
        <DataTable :rows="assignments" :columns="[{ key: 'code', label: 'Assignment', primary: true, format: (v, r) => `${r.collaborator?.name} · ${r.package?.name}` }, { key: 'status', label: 'Status', secondary: true }, { key: 'effective_from', label: 'From', format: date }, { key: 'effective_through', label: 'Through', format: date }, { key: 'earning_method', label: 'Method' }, { key: 'notes', label: 'Notes', hideMobile: true }]">
            <template #cell-status="{ value }"><Badge :label="value" /></template>
        </DataTable>
    </Card>
    <Card v-else-if="tab === 'collaborators'" :padded="false">
        <DataTable :rows="collaborators" :columns="[{ key: 'name', label: 'Collaborator', primary: true }, { key: 'status', label: 'Status', secondary: true }, { key: 'code', label: 'ID' }, { key: 'agreement_start', label: 'Agreement start', format: date }, { key: 'agreement_end', label: 'End', format: date }]">
            <template #cell-status="{ value }"><Badge :label="value" /></template>
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
</template>
