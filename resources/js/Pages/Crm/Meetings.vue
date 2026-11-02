<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import LeadCard from '@/Components/crm/LeadCard.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { dateTime } from '@/lib/format';

defineProps({ scheduled: Array, time_pending: Array });
</script>

<template>
    <Head title="Meetings" />
    <PageHeader title="Meetings & office visits" subtitle="Scheduled meetings and visits still waiting for a fixed time" />
    <div class="grid gap-4 lg:grid-cols-2">
        <Card title="Scheduled" :subtitle="`${scheduled.length} meetings`" :padded="false">
            <EmptyState v-if="!scheduled.length" message="No meetings scheduled." icon="calendar" />
            <div v-else class="grid gap-3 p-4">
                <LeadCard v-for="l in scheduled" :key="l.id" :lead="l"><p class="text-xs font-medium text-info">{{ l.meeting_at ? dateTime(l.meeting_at) : 'Time not set — add it on the lead' }}<template v-if="l.meeting_details"> · {{ l.meeting_details }}</template></p></LeadCard>
            </div>
        </Card>
        <Card title="Office visits — time pending" :subtitle="`${time_pending.length} leads`" :padded="false">
            <EmptyState v-if="!time_pending.length" message="No pending visits." icon="calendar" />
            <div v-else class="grid gap-3 p-4">
                <LeadCard v-for="l in time_pending" :key="l.id" :lead="l" />
            </div>
        </Card>
    </div>
</template>
