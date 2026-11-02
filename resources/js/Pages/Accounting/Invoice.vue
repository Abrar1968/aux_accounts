<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import PrintLayout from '@/Components/print/PrintLayout.vue';
import { exportInvoicePdf } from '@/lib/exports';
import { printPage } from '@/lib/pdf';
import { date, money } from '@/lib/format';

const props = defineProps({ invoice: Object });
const inv = computed(() => props.invoice);
const m = (v) => money(v, { decimals: true });
const tone = { PAID: 'positive', 'PARTIALLY PAID': 'warning', UNPAID: 'negative' };
</script>

<template>
    <Head :title="`Invoice ${inv.number}`" />
    <PageHeader :title="`Invoice ${inv.number}`" :subtitle="`${inv.customer} · ${inv.title}`" back="/accounting/receivables">
        <Badge :label="inv.status" :tone="tone[inv.status]" />
        <Button variant="secondary" size="sm" icon="doc" :href="`/accounting/receivables/${inv.type}/${inv.id}/statement`">Statement</Button>
        <Button variant="secondary" size="sm" icon="download" @click="exportInvoicePdf(inv)">PDF</Button>
        <Button size="sm" icon="printer" @click="printPage(`Invoice ${inv.number}`)">Print</Button>
    </PageHeader>

    <PrintLayout title="Invoice" :subtitle="inv.number" :watermark="inv.status"
        :meta="[{ label: 'Issued', value: date(inv.issued) }, { label: 'Reference', value: inv.reference }, { label: 'Status', value: inv.status }]"
        :client="inv.client_signature" :client-label="inv.client_signature ? 'Client signature' : null">
        <div class="mb-6">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-3">Bill to</p>
            <p class="mt-1 text-base font-semibold">{{ inv.customer }}</p>
            <p class="text-sm text-ink-2">{{ inv.title }}</p>
        </div>

        <table class="w-full text-sm">
            <thead class="border-b border-line text-[11px] uppercase tracking-wider text-ink-3"><tr><th class="py-2 text-left">Description</th><th class="py-2 text-right">Amount</th></tr></thead>
            <tbody class="divide-y divide-line">
                <tr v-for="(l, i) in inv.lines" :key="i">
                    <td class="py-2.5"><span class="block font-medium">{{ l.description }}</span><span v-if="l.detail" class="text-xs text-ink-3">{{ l.detail }}</span></td>
                    <td class="num py-2.5 text-right">{{ m(l.amount) }}</td>
                </tr>
            </tbody>
        </table>

        <dl class="ml-auto mt-4 w-full max-w-xs space-y-1.5 text-sm">
            <div class="flex justify-between"><dt class="text-ink-2">Invoice total</dt><dd class="num font-medium">{{ m(inv.total) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-2">Paid to date</dt><dd class="num font-medium text-positive">{{ m(inv.paid) }}</dd></div>
            <div class="flex justify-between rounded-lg bg-surface-2 px-3 py-2 text-base font-semibold"><dt>{{ inv.balance >= 0 ? 'Balance due' : 'Advance (credit)' }}</dt><dd class="num">{{ m(Math.abs(inv.balance)) }}</dd></div>
        </dl>

        <template v-if="inv.payments.length">
            <h3 class="mb-2 mt-8 text-sm font-semibold">Payments received</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[28rem] text-sm">
                    <thead class="text-left text-[11px] uppercase tracking-wider text-ink-3"><tr><th class="py-1.5">Date</th><th class="py-1.5">Receipt</th><th class="py-1.5">Method</th><th class="py-1.5 text-right">Amount</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="p in inv.payments" :key="p.code">
                            <td class="whitespace-nowrap py-1.5">{{ date(p.date) }}</td><td class="py-1.5 text-ink-2">{{ p.code }}</td><td class="py-1.5 text-ink-2">{{ p.method || '—' }}</td>
                            <td class="num py-1.5 text-right">{{ m(p.amount) }}<span v-if="p.fee" class="block text-[11px] text-ink-3">incl. fee {{ m(p.fee) }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
        <p class="mt-6 text-xs text-ink-3">Please quote {{ inv.number }} with your payment. Thank you for your business.</p>
    </PrintLayout>
</template>
