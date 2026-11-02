<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import { dateTime } from '@/lib/format';

defineProps({ logs: Object, filters: [Object, Array] });
const changes = (r) => Object.keys(r.after ?? r.before ?? {}).filter((k) => !['created_at', 'updated_at'].includes(k)).slice(0, 6).join(', ');
</script>

<template>
    <Head title="Audit log" />
    <PageHeader title="Audit log" subtitle="Every create, update and delete with the user and time" />
    <Card :padded="false">
        <DataTable dense :rows="logs.data" empty="No changes recorded yet."
            :columns="[{ key: 'auditable_type', label: 'Record', primary: true, format: (v, r) => `${v} #${r.auditable_id}` }, { key: 'action', label: 'Action', secondary: true }, { key: 'user', label: 'User', format: (v) => v?.name ?? 'System' }, { key: 'created_at', label: 'When', format: dateTime }, { key: 'fields', label: 'Fields', format: (v, r) => changes(r) }, { key: 'reason', label: 'Reason', hideMobile: true }]">
            <template #cell-action="{ value }"><Badge :tone="value === 'deleted' ? 'negative' : value === 'created' ? 'positive' : 'info'" :label="value" /></template>
        </DataTable>
        <Pagination :page="logs" />
    </Card>
</template>
