<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import MonthPicker from '@/Components/ui/MonthPicker.vue';
import PaymentDrawer from '@/Components/accounting/PaymentDrawer.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { money, monthLabel } from '@/lib/format';

const props = defineProps({ month: String, payrolls: Object });
const { can } = usePermissions();
const month = computed({ get: () => props.month, set: (v) => v && router.get('/accounting/payroll', { month: v }, { preserveScroll: true }) });
const rows = computed(() => props.payrolls.data);
const total = (k) => rows.value.reduce((s, r) => s + Number(r[k] ?? 0), 0);
const paying = ref(null);
const draft = () => router.post(`/accounting/payroll/${props.month}/draft`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Payroll" />
    <PageHeader title="Payroll" :subtitle="`${monthLabel(month)} · gross salary is the expense; advance recoveries are non-cash`">
        <MonthPicker v-model="month" />
        <Button v-if="can('accounting.payroll.create')" size="sm" variant="secondary" icon="plus" @click="draft">Draft from base salaries</Button>
    </PageHeader>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Gross salary" :value="money(total('gross_salary'))" :hint="`${rows.length} employees`" />
        <StatCard label="Advance recoveries" :value="money(total('advance_recovery'))" />
        <StatCard label="Cash settled" :value="money(total('salary_cash_settled'))" />
        <StatCard label="Unpaid" :value="money(total('unpaid'))" :tone="total('unpaid') > 0 ? 'warning' : 'positive'" />
    </div>
    <Card :padded="false" class="mt-4">
        <DataTable :rows="rows" empty="No payroll rows for this month — draft them from base salaries."
            :footer="{ employee: 'Total', gross_salary: money(total('gross_salary')), advance_recovery: money(total('advance_recovery')), salary_cash_settled: money(total('salary_cash_settled')), unpaid: money(total('unpaid')) }"
            :columns="[{ key: 'employee', label: 'Employee', primary: true }, { key: 'status', label: 'Status', secondary: true }, { key: 'gross_salary', label: 'Gross', align: 'right', format: (v) => money(v) }, { key: 'advance_recovery', label: 'Recovery', align: 'right', format: (v) => money(v) }, { key: 'salary_cash_settled', label: 'Paid', align: 'right', format: (v) => money(v) }, { key: 'unpaid', label: 'Unpaid', align: 'right', format: (v) => money(v) }, { key: 'actions', label: '', align: 'right', hideMobile: false }]">
            <template #cell-status="{ value }"><Badge :label="value" /></template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <Button v-if="row.unpaid > 0 && can('accounting.payroll.pay')" size="sm" @click.stop="paying = row">Pay</Button>
                    <Link :href="`/accounting/payroll/${row.id}/payslip`" class="inline-flex h-8 items-center rounded-lg px-2 text-xs font-medium text-brand-600 hover:bg-surface-2">Payslip</Link>
                </div>
            </template>
        </DataTable>
    </Card>
    <PaymentDrawer :model-value="!!paying" @update:model-value="(v) => !v && (paying = null)" :url="`/accounting/payroll/${paying?.id}/payments`" title="Record salary payment" :subtitle="paying?.code" :remaining="paying?.unpaid ?? 0" />
</template>
