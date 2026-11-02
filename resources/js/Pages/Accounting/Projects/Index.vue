<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import RowActions from '@/Components/ui/RowActions.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import ProjectDrawer from '@/Components/accounting/ProjectDrawer.vue';
import { useProjectCrud } from '@/Components/accounting/ProjectDrawer.vue';
import { useFilters } from '@/Composables/useFilters';
import { usePermissions } from '@/Composables/usePermissions';
import { money, pct } from '@/lib/format';

const props = defineProps({ projects: Object, filters: Object });
const { filters } = useFilters({ q: props.filters.q ?? '', kind: props.filters.kind ?? '' });
const { can } = usePermissions();
const columns = [
    { key: 'name', label: 'Project', primary: true, avatar: true, sub: (r) => `${r.code}${r.customer && r.customer !== r.name ? ` · ${r.customer}` : ''}` },
    { key: 'payment_status', label: 'Payment', secondary: true },
    { key: 'kind', label: 'Type', format: (v, r) => r.type_label },
    { key: 'total_value', label: 'Contract', align: 'right', format: (v) => money(v) },
    { key: 'revenue_recognized', label: 'Recognised', align: 'right', format: (v) => money(v) },
    { key: 'current_ar', label: 'A/R', align: 'right', format: (v) => money(v) },
    { key: 'collection_percent', label: 'Collected', align: 'right', format: (v) => pct(v) },
    { key: 'work_status', label: 'Work' },
];
const crud = useProjectCrud();

</script>

<template>
    <Head title="Projects" />
    <PageHeader title="Projects" subtitle="Domestic and international registers · revenue, collections and receivables">
        <Button v-if="can('accounting.projects.create')" icon="plus" class="hidden sm:inline-flex" @click="crud.create()">New project</Button>
    </PageHeader>
    <Card :padded="false">
        <FilterBar v-model:q="filters.q" placeholder="Search name, code, customer…">
            <select v-model="filters.kind" class="rounded-lg border border-line bg-surface px-3 text-sm" aria-label="Type">
                <option value="">All types</option><option value="project">Domestic</option><option value="international">International</option><option value="lead">Leads</option>
            </select>
        </FilterBar>
        <DataTable :columns="columns" :rows="projects.data" clickable @row="(r) => router.visit(`/accounting/projects/${r.id}`)">
            <template #cell-payment_status="{ value }"><Badge :label="value" /></template>
            <template #cell-work_status="{ value }"><Badge :label="value" /></template>
            <template v-if="can('accounting.projects.update') || can('accounting.projects.delete')" #actions="{ row }">
                <RowActions :edit="can('accounting.projects.update')" :remove="can('accounting.projects.delete')" @edit="crud.edit(row)" @remove="crud.askDelete(row)" />
            </template>
        </DataTable>
        <Pagination :page="projects" />
    </Card>
    <BottomBar v-if="can('accounting.projects.create')"><Button icon="plus" @click="crud.create()">New project</Button></BottomBar>

    <ProjectDrawer :crud="crud" />
    <ConfirmDialog v-model="crud.confirm.open" danger reason :loading="crud.confirm.loading" title="Delete project?" confirm-label="Delete project"
        :message="`${crud.confirm.row?.name} (${crud.confirm.row?.code}) will be removed with its unpaid referral rows and open-period recognition. Projects with customer receipts or historical revenue cannot be deleted.`" @confirm="crud.doDelete" />
</template>
