<script setup>
// Shared body for Analytics and printable CRM reports.
import Funnel from '@/Components/charts/Funnel.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { money, pct, titleCase } from '@/lib/format';

defineProps({ data: { type: Object, required: true } });
const breakdowns = ['by_owner', 'by_source', 'by_service', 'by_business_type', 'by_industry', 'by_outcome'];
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5 print:grid-cols-5">
            <StatCard label="New leads" :value="String(data.new_leads)" />
            <StatCard label="Calls" :value="String(data.calls)" :hint="`${data.detailed_calls} detailed`" />
            <StatCard label="Wins" :value="String(data.wins)" tone="positive" :hint="`Losses ${data.losses}`" />
            <StatCard label="Deal value" :value="money(data.deal_value)" />
            <StatCard label="Cohort conversion" :value="pct(data.cohort_conversion, 1)" />
        </div>
        <div class="grid gap-4 lg:grid-cols-3 print:grid-cols-2">
            <Card title="Funnel" subtitle="Leads in this cohort by current stage"><Funnel :stages="data.funnel" /></Card>
            <Card v-for="b in breakdowns" :key="b" :title="titleCase(b.replace('by_', 'By '))" :padded="false" class="break-inside-avoid">
                <table class="w-full text-sm">
                    <thead class="text-[11px] uppercase tracking-wider text-ink-3"><tr><th class="px-4 py-2 text-left">Group</th><th class="px-4 py-2 text-right">Leads</th><th class="px-4 py-2 text-right">Won</th></tr></thead>
                    <tbody class="divide-y divide-line"><tr v-for="r in data[b].slice(0, 8)" :key="r.label"><td class="max-w-0 truncate px-4 py-1.5 print:max-w-none print:whitespace-normal">{{ r.label }}</td><td class="num px-4 py-1.5 text-right">{{ r.leads }}</td><td class="num px-4 py-1.5 text-right">{{ r.won }}</td></tr></tbody>
                </table>
            </Card>
        </div>
    </div>
</template>
