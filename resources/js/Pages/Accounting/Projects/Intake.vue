<script setup>
// Printable Project Intake Form — same sections as the company's Word intake form.
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import PrintLayout from '@/Components/print/PrintLayout.vue';
import { exportIntakePdf } from '@/lib/exports';
import { printPage } from '@/lib/pdf';
import { date, money } from '@/lib/format';

const props = defineProps({ project: Object });
const p = computed(() => props.project.data);
const m = (v) => money(v, { decimals: true });
const provider = (v) => ({ client: 'Provided by client', auxtech: 'Provided by Auxtech', not_needed: 'Not needed' })[v] ?? 'Not specified';
const sections = computed(() => [
    ['Client information', [['Name', p.value.customer || p.value.name], ['Email', p.value.client_email], ['Phone', p.value.client_phone], ['Address', p.value.client_address]]],
    ['Project information', [['Title / Domain', [p.value.name, p.value.domain].filter(Boolean).join(' · ')], ['Project ID', p.value.code], ['Assign date', date(p.value.start_date)], ['Delivery date', date(p.value.est_delivery_date)], ['Project manager', p.value.project_manager]]],
    ['Domain & hosting', [['Domain', provider(p.value.domain_provided_by)], ['Hosting', provider(p.value.hosting_provided_by)]]],
    ['Payment information', [['Project valuation', m(p.value.base_value)], ...(Number(p.value.change_orders) ? [['Change orders', m(p.value.change_orders)]] : []), ['Discount', m(p.value.discount)], ['Contract value', m(p.value.total_value)], ['Paid', m(p.value.gross_settlement)], ['Due', m(Math.max(0, p.value.total_value - p.value.gross_settlement))]]],
]);
</script>

<template>
    <Head :title="`Intake ${p.code}`" />
    <PageHeader title="Project intake form" :subtitle="`${p.code} · ${p.name}`" :back="`/accounting/projects/${p.id}`">
        <Button variant="secondary" size="sm" icon="download" @click="exportIntakePdf(p, sections)">PDF</Button>
        <Button size="sm" icon="printer" @click="printPage(`Intake ${p.code}`)">Print</Button>
    </PageHeader>
    <PrintLayout title="Project Intake Form" :subtitle="p.code" :meta="[{ label: 'Assign date', value: date(p.start_date) }, { label: 'Delivery', value: date(p.est_delivery_date) }]"
        :client="p.client_signature" client-label="Client signature" seal-label="Auxtech">
        <div class="grid gap-6 sm:grid-cols-2">
            <section v-for="[title, rows] in sections" :key="title" class="break-inside-avoid">
                <h3 class="mb-2 border-b border-line pb-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-brand-600 dark:text-brand-300 print:text-black">{{ title }}</h3>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                    <template v-for="[k, v] in rows" :key="k"><dt class="text-ink-3">{{ k }}</dt><dd class="min-w-0 break-words text-right font-medium" :class="['Contract value', 'Due'].includes(k) && 'num font-bold'">{{ v || '—' }}</dd></template>
                </dl>
            </section>
        </div>
        <section v-if="p.notes" class="mt-6 break-inside-avoid">
            <h3 class="mb-2 border-b border-line pb-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-brand-600 dark:text-brand-300 print:text-black">Note</h3>
            <p class="whitespace-pre-line text-sm text-ink-2">{{ p.notes }}</p>
        </section>
    </PrintLayout>
</template>
