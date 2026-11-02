<script setup>
import { computed, ref } from 'vue';
import Icon from '@/Components/ui/Icon.vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FileDrop from '@/Components/ui/FileDrop.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import PaymentDrawer from '@/Components/accounting/PaymentDrawer.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useCrud } from '@/Composables/useCrud';
import { usePermissions } from '@/Composables/usePermissions';
import { date, dateTime, money, monthLabel } from '@/lib/format';

const props = defineProps({ expense: Object, payments: Object, attachments: Object });
const e = computed(() => props.expense.data);
const { can } = usePermissions();
const pay = ref(false);
const del = useCrud({ base: '/accounting/expenses', blank: {} });
const rev = useCrud({ base: '', blank: {}, deleteUrl: (r) => `/accounting/expense-payments/${r.id}/reverse` });
// reversal is a POST (the ledger is permanent) — reuse the confirm dialog state
function doReverse(reason) {
    rev.confirm.loading = true;
    router.post(`/accounting/expense-payments/${rev.confirm.row.id}/reverse`, { reason }, { preserveScroll: true, onFinish: () => { rev.confirm.loading = false; rev.confirm.open = false; } });
}
const reversed = computed(() => new Set(props.payments.data.map((p) => p.reverses_id).filter(Boolean)));
const canReverse = (p) => can('accounting.expenses.pay') && Number(p.amount) > 0 && !p.reverses_id && !reversed.value.has(p.id);
const payable = computed(() => can('accounting.expenses.pay') && e.value.payable > 0 && e.value.subcategory !== 'Processing fee');
</script>

<template>
    <Head :title="e.code" />
    <PageHeader :title="e.description || e.code" :subtitle="`${e.code} · ${e.category}${e.subcategory ? ' / ' + e.subcategory : ''}`" back="/accounting/expenses">
        <Badge :label="e.payment_status" />
        <Button v-if="can('accounting.expenses.delete')" variant="danger-soft" size="sm" icon="trash" @click="del.askDelete(e)">Delete</Button>
        <Button v-if="payable" icon="wallet" class="hidden sm:inline-flex" @click="pay = true">Record payment</Button>
    </PageHeader>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard icon="receipt" label="Incurred" :value="money(e.amount)" :hint="`Accrual ${monthLabel(e.accrual_month)}`" />
        <StatCard icon="check" label="Settled" :value="money(e.total_settled)" />
        <StatCard icon="clock" label="Payable" :value="money(e.payable)" :tone="e.payable > 0 ? 'warning' : 'positive'" />
        <StatCard icon="shield" label="Entry check" :value="e.entry_check" :tone="e.entry_check === 'OK' ? 'positive' : 'negative'" :hint="`Expense date ${date(e.expense_date)}`" />
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <Card title="Payments" :padded="false" class="lg:col-span-2">
            <DataTable dense :rows="payments.data" empty="No payments recorded."
                :columns="[{ key: 'paid_on', label: 'Paid on', primary: true, format: date }, { key: 'amount', label: 'Amount', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'origin', label: 'Origin' }, { key: 'method', label: 'Method', format: (v, r) => [v, r.account].filter(Boolean).join(' · ') || '—' }, { key: 'posted_at', label: 'Posted', format: dateTime, hideMobile: true }]">
                <template #cell-origin="{ row, value }"><Badge :label="value" :tone="row.reverses_id ? 'negative' : reversed.has(row.id) ? 'neutral' : 'brand'" /><span v-if="reversed.has(row.id)" class="ml-1.5 text-xs text-ink-3">reversed</span></template>
                <template #actions="{ row }">
                    <button v-if="canReverse(row)" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-ink-2 hover:bg-negative/10 hover:text-negative" @click="rev.askDelete(row)"><Icon name="reverse" :size="14" />Reverse</button>
                </template>
            </DataTable>
        </Card>
        <Card title="Receipts & evidence"><FileDrop type="expense" :id="e.id" :files="attachments.data" /></Card>
    </div>
    <p v-if="e.notes" class="card mt-4 p-4 text-sm text-ink-2">{{ e.notes }}</p>
    <BottomBar v-if="payable"><Button icon="wallet" @click="pay = true">Record payment</Button></BottomBar>
    <ConfirmDialog v-model="del.confirm.open" danger reason :loading="del.confirm.loading" title="Delete bill?" confirm-label="Delete bill"
        :message="`${e.code} (${money(e.amount)}) will be removed. Bills with payments must have them reversed first.`" @confirm="del.doDelete" />
    <ConfirmDialog v-model="rev.confirm.open" reason :loading="rev.confirm.loading" icon="reverse" title="Reverse this payment?" confirm-label="Post reversal"
        :message="`A negative entry of ${money(rev.confirm.row?.amount)} dated today will cancel ${rev.confirm.row?.code?.slice(0, 13)}… The original stays in the ledger for the audit trail.`" @confirm="doReverse" />
    <PaymentDrawer v-model="pay" :url="`/accounting/expenses/${e.id}/payments`" title="Record expense payment" :subtitle="e.code" :remaining="e.payable" />
</template>
