<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import PriorityBadge from '@/Components/crm/PriorityBadge.vue';
import { dateTime } from '@/lib/format';

const props = defineProps({ leads: Array });
const tab = ref('due');
const groups = computed(() => ({
    due: props.leads.filter((l) => ['OVERDUE', 'TODAY'].includes(l.priority)),
    upcoming: props.leads.filter((l) => !['OVERDUE', 'TODAY'].includes(l.priority)),
    all: props.leads,
}));
</script>

<template>
    <Head title="Follow-ups" />
    <PageHeader title="Follow-up queue" :subtitle="`${leads.length} open follow-ups, oldest first`" />
    <div class="mb-3"><Tabs v-model="tab" :tabs="[{ key: 'due', label: 'Due & overdue', count: groups.due.length }, { key: 'upcoming', label: 'Upcoming', count: groups.upcoming.length }, { key: 'all', label: 'All', count: leads.length }]" /></div>
    <Card :padded="false">
        <DataTable :rows="groups[tab]" clickable @row="(r) => router.visit(`/crm/leads/${r.id}`)" empty="Nothing in this queue."
            :columns="[{ key: 'phone', label: 'Lead', primary: true }, { key: 'priority', label: 'Priority', secondary: true }, { key: 'next_followup', label: 'Due', format: dateTime }, { key: 'outcome', label: 'Outcome' }, { key: 'stage', label: 'Stage' }, { key: 'owner', label: 'Owner' }, { key: 'service', label: 'Service', hideMobile: true }]">
            <template #cell-phone="{ row }"><span class="num block font-medium">{{ row.phone }}</span><span class="block truncate text-xs text-ink-3">{{ row.business_type }}</span></template>
            <template #cell-priority="{ value }"><PriorityBadge :priority="value" /></template>
            <template #cell-stage="{ value }"><Badge :label="value" /></template>
        </DataTable>
    </Card>
</template>
