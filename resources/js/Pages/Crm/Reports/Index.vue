<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import { date, dateTime, todayIso } from '@/lib/format';

defineProps({ reports: Object });
const form = useForm({ type: 'Daily', date: todayIso() });
</script>

<template>
    <Head title="CRM reports" />
    <PageHeader title="CRM reports" subtitle="Daily, monthly and yearly snapshots — immutable and de-duplicated by hash">
        <form class="flex flex-wrap items-center gap-2" @submit.prevent="form.post('/crm/reports')">
            <select v-model="form.type" class="h-9 rounded-lg border border-line bg-surface px-2 text-sm" aria-label="Report type"><option>Daily</option><option>Monthly</option><option>Yearly</option></select>
            <input v-model="form.date" type="date" class="h-9 rounded-lg border border-line bg-surface px-2 text-sm" aria-label="Date" />
            <Button type="submit" size="sm" :loading="form.processing">Generate</Button>
        </form>
    </PageHeader>
    <p v-if="form.errors.date" class="mb-3 text-sm text-negative">{{ form.errors.date }}</p>
    <Card :padded="false">
        <DataTable :rows="reports.data" clickable @row="(r) => router.visit(`/crm/reports/${r.id}`)" empty="No reports yet — the scheduler creates them after each day, month and year closes."
            :columns="[{ key: 'type', label: 'Report', primary: true, format: (v, r) => `${v} · ${date(r.period_start)} – ${date(r.period_end)}` }, { key: 'status', label: 'Status', secondary: true }, { key: 'mode', label: 'Mode' }, { key: 'hash', label: 'Revision', format: (v) => v.slice(0, 12) }, { key: 'created_at', label: 'Created', format: dateTime }]">
            <template #cell-status="{ value }"><Badge :tone="value === 'CLOSED' ? 'positive' : 'warning'" :label="value" /></template>
        </DataTable>
        <Pagination :page="reports" />
    </Card>
</template>
