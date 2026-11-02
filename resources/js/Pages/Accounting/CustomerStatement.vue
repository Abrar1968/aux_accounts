<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import PrintLayout from '@/Components/print/PrintLayout.vue';
import { exportTablePdf } from '@/lib/exports';
import { printPage } from '@/lib/pdf';
import { date, money } from '@/lib/format';

const props = defineProps({ statement: Object });
const s = computed(() => props.statement);
const pdf = () => exportTablePdf(`Statement of account - ${s.value.customer}`,
    [{ label: 'Date', width: 70 }, { label: 'Description', width: 215 }, { label: 'Debit', width: 75, align: 'right' }, { label: 'Credit', width: 75, align: 'right' }, { label: 'Balance', width: 80, align: 'right' }],
    [['', 'Opening balance', '', '', money(s.value.opening)], ...s.value.lines.map((l) => [date(l.date), `${l.description} (${l.reference})`, l.debit ? money(l.debit) : '', l.credit ? money(l.credit) : '', money(l.balance)]), ['', 'Balance due', '', '', money(s.value.closing)]],
    `statement-${s.value.code}.pdf`);
</script>

<template>
    <Head :title="`Statement ${s.customer}`" />
    <PageHeader :title="`Statement of account — ${s.customer}`" :subtitle="s.code" back="/accounting/receivables">
        <Button variant="secondary" size="sm" icon="download" @click="pdf">PDF</Button>
        <Button size="sm" icon="printer" @click="printPage(`Statement ${s.customer} ${s.as_of}`)">Print</Button>
    </PageHeader>
    <PrintLayout title="Statement of account" :subtitle="s.customer" :meta="[{ label: 'Reference', value: s.code }, { label: 'Date', value: date(s.as_of) }, { label: 'Balance due', value: money(s.closing, { decimals: true }) }]">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[34rem] text-sm">
                <thead class="text-[11px] uppercase tracking-wider text-ink-3"><tr><th class="py-2 text-left">Date</th><th class="py-2 text-left">Description</th><th class="py-2 text-right">Debit</th><th class="py-2 text-right">Credit</th><th class="py-2 text-right">Balance</th></tr></thead>
                <tbody class="divide-y divide-line">
                    <tr class="text-ink-2"><td class="py-2" /><td class="py-2">Opening balance</td><td /><td /><td class="num py-2 text-right">{{ money(s.opening, { decimals: true }) }}</td></tr>
                    <tr v-for="l in s.lines" :key="l.reference">
                        <td class="whitespace-nowrap py-2">{{ date(l.date) }}</td>
                        <td class="py-2"><span class="block">{{ l.description }}</span><span class="text-xs text-ink-3">{{ l.reference }}</span></td>
                        <td class="num py-2 text-right">{{ l.debit ? money(l.debit, { decimals: true }) : '' }}</td>
                        <td class="num py-2 text-right text-positive">{{ l.credit ? money(l.credit, { decimals: true }) : '' }}</td>
                        <td class="num py-2 text-right font-medium">{{ money(l.balance, { decimals: true }) }}</td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-line font-semibold"><tr><td /><td class="py-2">Balance due</td><td class="num py-2 text-right">{{ money(s.total_debit, { decimals: true }) }}</td><td class="num py-2 text-right">{{ money(s.total_credit, { decimals: true }) }}</td><td class="num py-2 text-right">{{ money(s.closing, { decimals: true }) }}</td></tr></tfoot>
            </table>
        </div>
        <p class="mt-6 text-xs text-ink-3">A negative balance is a customer advance (paid ahead of delivery). Please quote the reference with your payment.</p>
    </PrintLayout>
</template>
