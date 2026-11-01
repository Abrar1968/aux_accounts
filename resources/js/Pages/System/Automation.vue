<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import { dateTime, relative } from '@/lib/format';

defineProps({ health: Array, runs: Object });
const summary = (r) => r.error ?? (r.summary ? Object.entries(r.summary).map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : v}`).join(' · ') : '');
</script>

<template>
    <Head title="Automation" />
    <PageHeader title="Automation" subtitle="Scheduled jobs (cron: php artisan schedule:run every minute)" />
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div v-for="h in health" :key="h.command" class="card p-4"><p class="num truncate text-sm font-medium">{{ h.command }}</p><p class="mt-1 text-xs text-ink-3">{{ h.last_success ? `Last OK ${relative(h.last_success)}` : 'No successful run yet' }}</p><Badge class="mt-2" :tone="h.stale ? 'warning' : 'positive'" :label="h.stale ? 'Check schedule' : 'Healthy'" /></div>
    </div>
    <Card title="Run history" :padded="false" class="mt-4">
        <DataTable dense :rows="runs.data" :columns="[{ key: 'command', label: 'Command', primary: true }, { key: 'status', label: 'Status', secondary: true }, { key: 'started_at', label: 'Started', format: dateTime }, { key: 'summary', label: 'Summary', format: (v, r) => summary(r), class: 'max-w-md truncate' }]">
            <template #cell-status="{ value }"><Badge :label="value" /></template>
        </DataTable>
        <Pagination :page="runs" />
    </Card>
</template>
