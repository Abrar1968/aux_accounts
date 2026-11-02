<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Input from '@/Components/ui/Input.vue';
import { useFilters } from '@/Composables/useFilters';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, pct } from '@/lib/format';

const props = defineProps({ rows: Object, filters: Object });
const { filters } = useFilters({ q: props.filters.q ?? '' });
const { can } = usePermissions();
const editing = ref(null);
const form = useForm({ ending_progress: '', override_amount: '', notes: '' });
function edit(r) {
    if (r.is_historical || !can('accounting.projects.update')) return;
    editing.value = r;
    form.ending_progress = r.ending_progress === null ? '' : r.ending_progress * 100;
    form.override_amount = r.override_amount ?? '';
    form.notes = r.notes ?? '';
}
const save = () => form.transform((d) => ({ ...d, ending_progress: d.ending_progress === '' ? null : Number(d.ending_progress) / 100, override_amount: d.override_amount === '' ? null : d.override_amount }))
    .put(`/accounting/recognition/${editing.value.id}`, { preserveScroll: true, onSuccess: () => (editing.value = null) });
const columns = [
    { key: 'code', label: 'Recognition', primary: true },
    { key: 'recognized_amount', label: 'Recognised', align: 'right', secondary: true, format: (v) => money(v) },
    { key: 'recognition_month', label: 'Month', format: date },
    { key: 'method_label', label: 'Method' },
    { key: 'ending_progress', label: 'Progress', align: 'right', format: (v, r) => (v === null ? '—' : `${pct(r.opening_progress ?? 0)} → ${pct(v)}`) },
    { key: 'siam_share', label: 'Siam share', align: 'right', format: (v) => money(v) },
    { key: 'entry_check', label: 'Check' },
];
</script>

<template>
    <Head title="Revenue recognition" />
    <PageHeader title="Revenue recognition" subtitle="Point-in-time rows post automatically on delivery; enter ending cumulative progress for over-time drafts" />
    <Card :padded="false">
        <FilterBar v-model:q="filters.q" placeholder="Search code or notes…" />
        <DataTable :columns="columns" :rows="rows.data" clickable @row="edit">
            <template #cell-code="{ row }"><span class="block font-medium">{{ row.code }}</span><span class="block truncate text-xs text-ink-3">{{ row.project?.name ?? row.notes }}</span></template>
            <template #cell-entry_check="{ value, row }"><Badge :label="value" /><Badge v-if="row.is_historical" tone="neutral" class="ml-1">Historical</Badge></template>
        </DataTable>
        <Pagination :page="rows" />
    </Card>
    <Modal :model-value="!!editing" @update:model-value="(v) => !v && (editing = null)" :title="`Update ${editing?.code}`">
        <div class="space-y-4">
            <Input v-model="form.ending_progress" type="number" min="0" max="100" step="0.1" label="Ending cumulative progress (%)" :error="form.errors.ending_progress" :hint="`Opening progress ${pct(editing?.opening_progress ?? 0)}`" />
            <Input v-model="form.override_amount" type="number" step="0.01" label="Documented override (৳)" hint="Only with documentation; replaces the auto amount" :error="form.errors.override_amount" />
            <Input v-model="form.notes" label="Notes" />
        </div>
        <template #footer><Button variant="secondary" @click="editing = null">Cancel</Button><Button :loading="form.processing" @click="save">Save</Button></template>
    </Modal>
</template>
