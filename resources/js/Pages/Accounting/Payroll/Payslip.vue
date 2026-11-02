<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import PrintLayout from '@/Components/print/PrintLayout.vue';
import { date, money, monthLabel } from '@/lib/format';
import { printPage } from '@/lib/pdf';
import { exportPayslipPdf, payslipStatus } from '@/lib/exports';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Icon from '@/Components/ui/Icon.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ payroll: Object, payments: Object, recoveries: Object });
const p = computed(() => props.payroll.data);
const { can } = usePermissions();
const reversed = computed(() => new Set(props.payments.data.map((s) => s.reverses_id).filter(Boolean)));
const canReverse = (s) => can('accounting.payroll.pay') && Number(s.amount) > 0 && !s.reverses_id && !reversed.value.has(s.id);
const rev = reactive({ open: false, row: null, loading: false });
function doReverse(reason) {
    rev.loading = true;
    router.post(`/accounting/salary-payments/${rev.row.id}/reverse`, { reason }, { preserveScroll: true, onFinish: () => { rev.loading = false; rev.open = false; } });
}
</script>

<template>
    <Head :title="`Payslip ${p.employee}`" />
    <PageHeader :title="`Payslip — ${p.employee}`" :subtitle="monthLabel(p.period)" back="/accounting/payroll">
        <Button variant="secondary" size="sm" icon="download" @click="exportPayslipPdf(p, payments.data)">PDF</Button>
        <Button size="sm" icon="printer" @click="printPage(`Payslip ${p.employee} ${monthLabel(p.period)}`)">Print</Button>
    </PageHeader>
    <PrintLayout title="Payslip" :subtitle="`${p.employee} · ${monthLabel(p.period)}`" :meta="[{ label: 'Payroll ID', value: p.code }, { label: 'Status', value: p.status }]"
        :watermark="payslipStatus(p.status)" seal-label="Authorised by" client-label="Employee signature">
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
                <tr v-for="s in payments.data" :key="s.id"><td class="py-1.5">{{ date(s.paid_on) }}</td><td class="py-1.5">{{ s.method ?? s.origin }}</td><td class="num py-1.5 text-right">{{ money(s.amount, { decimals: true }) }}<button v-if="canReverse(s)" class="no-print ml-2 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-semibold text-ink-3 hover:bg-negative/10 hover:text-negative" @click="rev.row = s; rev.open = true"><Icon name="reverse" :size="12" />Reverse</button><span v-else-if="reversed.has(s.id)" class="no-print ml-2 text-[11px] text-ink-3">reversed</span></td></tr>
                <tr v-if="!payments.data.length"><td colspan="3" class="py-2 text-ink-3">No payments yet.</td></tr>
            </tbody>
        </table>
    </PrintLayout>
    <ConfirmDialog v-model="rev.open" reason :loading="rev.loading" icon="reverse" title="Reverse salary payment?" confirm-label="Post reversal"
        :message="`A negative entry of ${money(rev.row?.amount)} dated today cancels this payment; the original stays in the ledger.`" @confirm="doReverse" />
</template>
