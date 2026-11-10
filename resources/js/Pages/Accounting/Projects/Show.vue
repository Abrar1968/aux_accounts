<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Modal from '@/Components/ui/Modal.vue';
import Input from '@/Components/ui/Input.vue';
import ImagePicker from '@/Components/ui/ImagePicker.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import ProjectDrawer, { useProjectCrud } from '@/Components/accounting/ProjectDrawer.vue';
import { router } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions';
import { date, dateTime, money, pct, todayIso } from '@/lib/format';

const props = defineProps({ project: Object, recognitions: Object, payments: Object, referrals: Array });
const p = computed(() => props.project.data);
const { can } = usePermissions();
const crud = useProjectCrud();
const deliver = ref(false);
const form = useForm({ actual_delivery_date: todayIso() });
const submit = () => form.post(`/accounting/projects/${p.value.id}/deliver`, { preserveScroll: true, onSuccess: () => (deliver.value = false) });
const sig = useForm({ client_signature: null });
const saveSig = () => sig.post(`/accounting/projects/${p.value.id}/signature`, { forceFormData: true, preserveScroll: true, onSuccess: () => sig.reset() });
const removeSig = () => router.delete(`/accounting/projects/${p.value.id}/signature`, { preserveScroll: true });
const facts = computed(() => [
    ['Code', p.value.code], ['Customer', p.value.customer], ['Register', p.value.type_label], ['Revenue method', p.value.revenue_method],
    ['Source month', date(p.value.source_month)], ['Start', date(p.value.start_date)], ['Est. delivery', date(p.value.est_delivery_date)],
    ['Actual delivery', date(p.value.actual_delivery_date)], ['Referrers', p.value.referrer ?? '—'], ['Referral memo', money(p.value.referral_memo)],
    ['Last payment', dateTime(p.value.last_payment)], ['Fully paid', dateTime(p.value.fully_paid_date)],
]);
</script>

<template>
    <Head :title="p.name" />
    <PageHeader :title="p.name" :subtitle="`${p.code} · ${p.work_status}`" back="/accounting/projects">
        <Badge :label="p.payment_status" />
        <Button v-if="p.kind !== 'lead'" variant="secondary" size="sm" icon="receipt" :href="`/accounting/receivables/project/${p.id}/invoice`">Invoice</Button>
        <Button v-if="p.kind !== 'lead'" variant="secondary" size="sm" icon="doc" :href="`/accounting/receivables/project/${p.id}/statement`">Statement</Button>
        <Button v-if="can('accounting.projects.update') && !p.actual_delivery_date && p.kind !== 'lead'" size="sm" icon="check" @click="deliver = true">Mark delivered</Button>
        <Button v-if="can('accounting.projects.update')" variant="secondary" size="sm" icon="pencil" @click="crud.edit(p)">Edit</Button>
        <Button v-if="can('accounting.projects.delete')" variant="danger-soft" size="sm" icon="trash" @click="crud.askDelete(p)">Delete</Button>
    </PageHeader>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard icon="briefcase" label="Contract value" :value="money(p.total_value)" :hint="p.change_orders ? `Incl. change orders ${money(p.change_orders)}` : null" />
        <StatCard icon="trend" label="Revenue recognised" :value="money(p.revenue_recognized)" :hint="`Progress ${pct(p.progress)}`" />
        <StatCard icon="inbox" label="Collected (gross)" :value="money(p.gross_settlement)" :hint="`${pct(p.collection_percent)} · fees ${money(p.processing_fees)}`" />
        <StatCard :label="p.customer_advance ? 'Customer advance' : 'Receivable'" :value="money(p.customer_advance || p.current_ar)" :tone="p.current_ar ? 'warning' : 'neutral'" />
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="min-w-0 space-y-4">
        <Card title="Details">
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <template v-for="[k, v] in facts" :key="k"><dt class="text-ink-3">{{ k }}</dt><dd class="min-w-0 truncate text-right font-medium">{{ v }}</dd></template>
            </dl>
            <p v-if="p.notes" class="mt-4 rounded-lg bg-surface-2 p-3 text-sm text-ink-2">{{ p.notes }}</p>
        </Card>
        <Card title="Client signature" subtitle="Printed on this client's invoices & statements">
            <ImagePicker v-if="can('accounting.projects.update')" v-model="sig.client_signature" :current="p.client_signature?.png" label="Signature image" :error="sig.errors.client_signature" />
            <img v-else-if="p.client_signature" :src="p.client_signature.png" alt="Client signature" class="max-h-20" />
            <p v-else class="text-sm text-ink-3">No signature uploaded.</p>
            <div v-if="can('accounting.projects.update')" class="mt-3 flex gap-2">
                <Button size="sm" :disabled="!sig.client_signature" :loading="sig.processing" @click="saveSig">Save signature</Button>
                <Button v-if="p.client_signature" size="sm" variant="ghost" @click="removeSig">Remove</Button>
            </div>
        </Card>
        </div>
        <div class="space-y-4 lg:col-span-2">
            <Card title="Revenue recognition" :padded="false">
                <DataTable dense :rows="recognitions.data" empty="Not recognised yet — point-in-time projects are recognised automatically on delivery."
                    :columns="[{ key: 'period', label: 'Period', primary: true, format: date }, { key: 'recognized_amount', label: 'Recognised', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'method_label', label: 'Method' }, { key: 'ending_progress', label: 'Progress', align: 'right', format: (v) => (v === null ? '—' : pct(v)) }, { key: 'entry_check', label: 'Check' }]">
                    <template #cell-entry_check="{ value }"><Badge :label="value" /></template>
                </DataTable>
            </Card>
            <Card v-if="referrals.length" title="Referral split" :subtitle="referrals.length > 1 ? `Memo split equally between ${referrals.length} people` : 'Single referrer'" :padded="false">
                <DataTable dense :rows="referrals" :columns="[{ key: 'referrer', label: 'Referrer', primary: true }, { key: 'memo_amount', label: 'Share', align: 'right', secondary: true, format: (v) => money(v, { decimals: true }) }, { key: 'paid_amount', label: 'Paid', align: 'right', format: (v) => money(v) }, { key: 'status', label: 'Status' }, { key: 'code', label: 'ID' }]">
                    <template #cell-status="{ value }"><Badge :label="value" /></template>
                </DataTable>
            </Card>
            <Card title="Customer payments" :padded="false">
                <DataTable dense :rows="payments.data" empty="No receipts yet."
                    :columns="[{ key: 'paid_at', label: 'Date', primary: true, format: dateTime }, { key: 'cash_received', label: 'Cash', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'processing_fee', label: 'Fee', align: 'right', format: (v) => money(v) }, { key: 'method', label: 'Method' }, { key: 'code', label: 'Payment ID' }]" />
            </Card>
        </div>
    </div>
    <Modal v-model="deliver" title="Mark project delivered">
        <p class="mb-3 text-sm text-ink-2">Point-in-time revenue is recognised in the delivery month by the daily automation.</p>
        <Input v-model="form.actual_delivery_date" type="date" label="Actual delivery date" :max="todayIso()" :error="form.errors.actual_delivery_date" />
        <template #footer><Button variant="secondary" @click="deliver = false">Cancel</Button><Button :loading="form.processing" @click="submit">Save delivery</Button></template>
    </Modal>
    <ProjectDrawer :crud="crud" />
    <ConfirmDialog v-model="crud.confirm.open" danger reason :loading="crud.confirm.loading" title="Delete project?" confirm-label="Delete project"
        :message="`${p.name} (${p.code}) will be removed with its unpaid referral rows and open-period recognition. Projects with customer receipts or historical revenue cannot be deleted.`" @confirm="crud.doDelete" />
</template>
