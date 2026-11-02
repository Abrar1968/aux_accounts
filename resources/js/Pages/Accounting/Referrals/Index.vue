<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Modal from '@/Components/ui/Modal.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, todayIso } from '@/lib/format';

defineProps({ referrals: Object });
const { can } = usePermissions();
const editing = ref(null);
const form = useForm({ paid_on: todayIso(), paid_amount: '', notes: '' });
function edit(r) { if (!can('accounting.payroll.pay')) return; editing.value = r; form.paid_on = r.paid_on ?? todayIso(); form.paid_amount = r.paid_amount || r.memo_amount; form.notes = r.notes ?? ''; }
const save = () => form.put(`/accounting/referrals/${editing.value.id}`, { preserveScroll: true, onSuccess: () => (editing.value = null) });
</script>

<template>
    <Head title="Referrals" />
    <PageHeader title="Referrals" subtitle="Memo from the policy tiers · expensed only when actually paid" />
    <Card :padded="false">
        <DataTable :rows="referrals.data" clickable @row="edit"
            :columns="[{ key: 'project', label: 'Project', primary: true, format: (v) => v?.name }, { key: 'status', label: 'Status', secondary: true }, { key: 'referrer', label: 'Referrer' }, { key: 'memo_amount', label: 'Memo (share)', align: 'right', format: (v, r) => money(v) + (r.split > 1 ? ` · 1/${r.split}` : '') }, { key: 'paid_amount', label: 'Paid', align: 'right', format: (v) => money(v) }, { key: 'paid_on', label: 'Paid on', format: date }]">
            <template #cell-status="{ value }"><Badge :label="value" /></template>
        </DataTable>
    </Card>
    <Modal :model-value="!!editing" @update:model-value="(v) => !v && (editing = null)" :title="`Pay referral · ${editing?.referrer}`">
        <div class="space-y-4">
            <Input v-model="form.paid_on" type="date" label="Paid on" :max="todayIso()" :error="form.errors.paid_on" />
            <MoneyInput v-model="form.paid_amount" label="Amount paid" :hint="`Memo ${money(editing?.memo_amount)}${editing?.split > 1 ? ` — equal share (1 of ${editing.split} referrers)` : ''}`" :error="form.errors.paid_amount" />
            <Input v-model="form.notes" label="Notes" />
        </div>
        <template #footer><Button variant="secondary" @click="editing = null">Cancel</Button><Button :loading="form.processing" @click="save">Save payment</Button></template>
    </Modal>
</template>
