<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ReportBody from '@/Components/crm/ReportBody.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({ analytics: Object });
const range = reactive({ from: props.analytics.period.start, to: props.analytics.period.end });
const apply = () => router.get('/crm/analytics', range, { preserveScroll: true });
</script>

<template>
    <Head title="CRM analytics" />
    <PageHeader title="Analytics" subtitle="Cohort by lead date · wins by closed date · calls include count updates">
        <input v-model="range.from" type="date" class="h-9 rounded-lg border border-line bg-surface px-2 text-sm" aria-label="From" />
        <input v-model="range.to" type="date" class="h-9 rounded-lg border border-line bg-surface px-2 text-sm" aria-label="To" />
        <Button size="sm" @click="apply">Apply</Button>
    </PageHeader>
    <ReportBody :data="analytics" />
</template>
