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
import BottomBar from '@/Components/ui/BottomBar.vue';
import PriorityBadge from '@/Components/crm/PriorityBadge.vue';
import LeadFormDrawer from '@/Components/crm/LeadFormDrawer.vue';
import { useFilters } from '@/Composables/useFilters';
import { usePermissions } from '@/Composables/usePermissions';
import { date, shortDate } from '@/lib/format';

const props = defineProps({ leads: Object, options: Object, filters: Object });
const { filters } = useFilters({ q: props.filters.q ?? '', stage: props.filters.stage ?? '', owner: props.filters.owner ?? '', source: props.filters.source ?? '' });
const { can } = usePermissions();
const adding = ref(false);
const stages = ['New', 'Contacted', 'Interested', 'Meeting', 'Proposal', 'Negotiation', 'Won', 'Lost'];
const columns = [
    { key: 'phone', label: 'Lead', primary: true },
    { key: 'priority', label: 'Priority', secondary: true },
    { key: 'outcome', label: 'Outcome' },
    { key: 'stage', label: 'Stage' },
    { key: 'next_followup', label: 'Next follow-up', format: shortDate },
    { key: 'calls_made', label: 'Calls', align: 'right' },
    { key: 'owner', label: 'Owner' },
    { key: 'lead_date', label: 'Lead date', format: date, hideMobile: true },
];
</script>

<template>
    <Head title="Leads" />
    <PageHeader title="Leads" :subtitle="`${leads.meta.total} leads · priority and follow-ups update live`">
        <Button v-if="can('crm.leads.create')" icon="plus" class="hidden sm:inline-flex" @click="adding = true">Add lead</Button>
    </PageHeader>
    <Card :padded="false">
        <FilterBar v-model:q="filters.q" placeholder="Search phone, code, notes…">
            <select v-model="filters.stage" class="rounded-lg border border-line bg-surface px-3 text-sm" aria-label="Stage"><option value="">All stages</option><option v-for="s in stages" :key="s">{{ s }}</option></select>
            <select v-model="filters.source" class="rounded-lg border border-line bg-surface px-3 text-sm" aria-label="Source"><option value="">All sources</option><option v-for="s in options.lists.source" :key="s">{{ s }}</option></select>
            <select v-model="filters.owner" class="rounded-lg border border-line bg-surface px-3 text-sm" aria-label="Owner"><option value="">All owners</option><option v-for="s in options.lists.owner" :key="s">{{ s }}</option></select>
        </FilterBar>
        <DataTable :columns="columns" :rows="leads.data" clickable @row="(r) => router.visit(`/crm/leads/${r.id}`)">
            <template #cell-phone="{ row }"><span class="num block font-medium">{{ row.phone }}</span><span class="block truncate text-xs text-ink-3">{{ row.business_type || '—' }}<template v-if="row.data_checks?.length"> · <span class="text-warning">{{ row.data_checks[0] }}</span></template></span></template>
            <template #cell-priority="{ value }"><PriorityBadge :priority="value" /></template>
            <template #cell-stage="{ value }"><Badge :label="value" /></template>
        </DataTable>
        <Pagination :page="leads" />
    </Card>
    <BottomBar v-if="can('crm.leads.create')"><Button icon="plus" @click="adding = true">Add lead</Button></BottomBar>
    <LeadFormDrawer v-model="adding" :options="options" />
</template>
