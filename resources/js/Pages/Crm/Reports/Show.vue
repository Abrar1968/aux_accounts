<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import PrintLayout from '@/Components/print/PrintLayout.vue';
import ReportBody from '@/Components/crm/ReportBody.vue';
import { date, dateTime } from '@/lib/format';
import { printPage } from '@/lib/pdf';

const props = defineProps({ report: Object });
const r = computed(() => props.report.data);
const title = computed(() => `${r.value.payload.brand ?? 'Sales & Marketing'} — ${r.value.type} report`);
</script>

<template>
    <Head :title="title" />
    <PageHeader :title="title" :subtitle="`${date(r.period_start)} – ${date(r.period_end)}`" back="/crm/reports">
        <Button size="sm" icon="printer" @click="printPage(`LeadCRM_${r.type}_${r.period_start}_${r.period_end}_${r.status}`)">Print / PDF</Button>
    </PageHeader>
    <PrintLayout :title="title" :subtitle="`${date(r.period_start)} – ${date(r.period_end)}`" :meta="[{ label: 'Status', value: r.status }, { label: 'Revision', value: r.hash.slice(0, 12) }, { label: 'Generated', value: dateTime(r.created_at) }]">
        <ReportBody :data="r.payload" />
    </PrintLayout>
</template>
