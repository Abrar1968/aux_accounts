<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import PrintLayout from '@/Components/print/PrintLayout.vue';
import { date, money, monthLabel } from '@/lib/format';
import { printPage } from '@/lib/pdf';

const props = defineProps({ payroll: Object, payments: Object, recoveries: Object });
const p = computed(() => props.payroll.data);
</script>

<template>
    <Head :title="`Payslip ${p.employee}`" />
    <PageHeader :title="`Payslip — ${p.employee}`" :subtitle="monthLabel(p.period)" back="/accounting/payroll">
        <Button size="sm" icon="printer" @click="printPage(`Payslip ${p.employee} ${monthLabel(p.period)}`)">Print / PDF</Button>
    </PageHeader>
    <PrintLayout title="Payslip" :subtitle="`${p.employee} · ${monthLabel(p.period)}`" :meta="[{ label: 'Payroll ID', value: p.code }, { label: 'Status', value: p.status }]">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-line">
                <tr><td class="py-2">Gross salary</td><td class="num py-2 text-right">{{ money(p.gross_salary, { decimals: true }) }}</td></tr>
                <tr><td class="py-2 text-ink-2">Less: advance recovery</td><td class="num py-2 text-right">({{ money(p.advance_recovery, { decimals: true }) }})</td></tr>
                <tr class="font-semibold"><td class="py-2">Net payable</td><td class="num py-2 text-right">{{ money(p.gross_salary - p.advance_recovery, { decimals: true }) }}</td></tr>
                <tr><td class="py-2 text-ink-2">Paid in cash / bank</td><td class="num py-2 text-right">{{ money(p.salary_cash_settled, { decimals: true }) }}</td></tr>
                <tr v-if="p.referral_paid"><td class="py-2 text-ink-2">Referral commission paid (separate)</td><td class="num py-2 text-right">{{ money(p.referral_paid, { decimals: true }) }}</td></tr>
                <tr class="font-semibold"><td class="py-2">Outstanding</td><td class="num py-2 text-right">{{ money(p.unpaid, { decimals: true }) }}</td></tr>
            </tbody>
        </table>
        <h3 class="mt-6 mb-2 text-sm font-semibold">Payments</h3>
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-ink-3"><tr><th class="py-1">Date</th><th class="py-1">Method</th><th class="py-1 text-right">Amount</th></tr></thead>
            <tbody class="divide-y divide-line">
                <tr v-for="s in payments.data" :key="s.id"><td class="py-1.5">{{ date(s.paid_on) }}</td><td class="py-1.5">{{ s.method ?? s.origin }}</td><td class="num py-1.5 text-right">{{ money(s.amount, { decimals: true }) }}</td></tr>
                <tr v-if="!payments.data.length"><td colspan="3" class="py-2 text-ink-3">No payments yet.</td></tr>
            </tbody>
        </table>
        <div class="mt-12 grid grid-cols-2 gap-10 text-xs text-ink-3"><p class="border-t border-line pt-2">Employee signature</p><p class="border-t border-line pt-2 text-right">Authorised by</p></div>
    </PrintLayout>
</template>
