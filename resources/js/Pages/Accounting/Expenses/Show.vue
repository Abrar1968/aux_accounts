<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FileDrop from '@/Components/ui/FileDrop.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import PaymentDrawer from '@/Components/accounting/PaymentDrawer.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { date, dateTime, money, monthLabel } from '@/lib/format';

const props = defineProps({ expense: Object, payments: Object, attachments: Object });
const e = computed(() => props.expense.data);
const { can } = usePermissions();
const pay = ref(false);
const payable = computed(() => can('accounting.expenses.pay') && e.value.payable > 0 && e.value.subcategory !== 'Processing fee');
</script>

<template>
    <Head :title="e.code" />
    <PageHeader :title="e.description || e.code" :subtitle="`${e.code} · ${e.category}${e.subcategory ? ' / ' + e.subcategory : ''}`" back="/accounting/expenses">
        <Badge :label="e.payment_status" />
        <Button v-if="payable" icon="wallet" class="hidden sm:inline-flex" @click="pay = true">Record payment</Button>
    </PageHeader>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Incurred" :value="money(e.amount)" :hint="`Accrual ${monthLabel(e.accrual_month)}`" />
        <StatCard label="Settled" :value="money(e.total_settled)" />
        <StatCard label="Payable" :value="money(e.payable)" :tone="e.payable > 0 ? 'warning' : 'positive'" />
        <StatCard label="Entry check" :value="e.entry_check" :tone="e.entry_check === 'OK' ? 'positive' : 'negative'" :hint="`Expense date ${date(e.expense_date)}`" />
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <Card title="Payments" :padded="false" class="lg:col-span-2">
            <DataTable dense :rows="payments.data" empty="No payments recorded."
                :columns="[{ key: 'paid_on', label: 'Paid on', primary: true, format: date }, { key: 'amount', label: 'Amount', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'origin', label: 'Origin' }, { key: 'method', label: 'Method', format: (v, r) => [v, r.account].filter(Boolean).join(' · ') || '—' }, { key: 'posted_at', label: 'Posted', format: dateTime, hideMobile: true }]" />
        </Card>
        <Card title="Receipts & evidence"><FileDrop type="expense" :id="e.id" :files="attachments.data" /></Card>
    </div>
    <p v-if="e.notes" class="card mt-4 p-4 text-sm text-ink-2">{{ e.notes }}</p>
    <BottomBar v-if="payable"><Button icon="wallet" @click="pay = true">Record payment</Button></BottomBar>
    <PaymentDrawer v-model="pay" :url="`/accounting/expenses/${e.id}/payments`" title="Record expense payment" :subtitle="e.code" :remaining="e.payable" />
</template>
