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
import RowActions from '@/Components/ui/RowActions.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import { useCrud } from '@/Composables/useCrud';
import { usePermissions } from '@/Composables/usePermissions';
import { money, monthLabel } from '@/lib/format';

const props = defineProps({ month: String, payrolls: Object });
const { can } = usePermissions();
const month = computed({ get: () => props.month, set: (v) => v && router.get('/accounting/payroll', { month: v }, { preserveScroll: true }) });
const rows = computed(() => props.payrolls.data);
const total = (k) => rows.value.reduce((s, r) => s + Number(r[k] ?? 0), 0);
const paying = ref(null);
const crud = useCrud({ base: '/accounting/payroll', blank: { gross_salary: '', notes: '' }, toForm: (r) => ({ gross_salary: r.gross_salary, notes: r.notes ?? '' }) });
const { open, editing, form } = crud;
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
            :columns="[{ key: 'employee', label: 'Employee', primary: true, avatar: true, sub: (r) => r.code }, { key: 'status', label: 'Status', secondary: true }, { key: 'gross_salary', label: 'Gross', align: 'right', format: (v) => money(v) }, { key: 'advance_recovery', label: 'Recovery', align: 'right', format: (v) => money(v) }, { key: 'salary_cash_settled', label: 'Paid', align: 'right', format: (v) => money(v) }, { key: 'unpaid', label: 'Unpaid', align: 'right', format: (v) => money(v) }, { key: 'actions', label: '', align: 'right', hideMobile: false }]">
            <template #cell-status="{ value }"><Badge :label="value" /></template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <Button v-if="row.unpaid > 0 && can('accounting.payroll.pay')" size="sm" @click.stop="paying = row">Pay</Button>
                    <Link :href="`/accounting/payroll/${row.id}/payslip`" class="inline-flex h-8 items-center rounded-lg px-2.5 text-xs font-semibold text-brand-600 hover:bg-brand-50 dark:text-brand-300">Payslip</Link>
                    <RowActions :edit="can('accounting.payroll.update')" :remove="can('accounting.payroll.delete')" @edit="crud.edit(row)" @remove="crud.askDelete(row)" />
                </div>
            </template>
        </DataTable>
    </Card>
    <PaymentDrawer :model-value="!!paying" @update:model-value="(v) => !v && (paying = null)" :url="`/accounting/payroll/${paying?.id}/payments`" title="Record salary payment" :subtitle="paying?.code" :remaining="paying?.unpaid ?? 0" />
    <Drawer v-model="open" icon="pencil" :title="`Edit ${editing?.employee ?? ''} · ${monthLabel(month)}`" :subtitle="editing?.code">
        <form id="pr-form" class="space-y-4" @submit.prevent="crud.submit()">
            <MoneyInput v-model="form.gross_salary" label="Gross salary" required :error="form.errors.gross_salary" />
            <Textarea v-model="form.notes" label="Notes" :rows="2" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="pr-form" :loading="form.processing">Save changes</Button></template>
    </Drawer>
    <ConfirmDialog v-model="crud.confirm.open" danger reason :loading="crud.confirm.loading" title="Delete payroll row?" confirm-label="Delete row"
        :message="`${crud.confirm.row?.code} will be removed. Rows with salary payments or advance recoveries must have those reversed first.`" @confirm="crud.doDelete" />
</template>
