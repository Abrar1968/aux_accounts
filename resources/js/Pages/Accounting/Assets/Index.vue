<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, monthLabel, todayIso } from '@/lib/format';

defineProps({ schedule: Array });
const { can } = usePermissions();
const open = ref(false);
const form = useForm({ name: '', category: 'Office Equipment', acquisition_date: todayIso(), in_service_date: todayIso(), cost: '', residual_value: 0, useful_life_months: 60, opening_accumulated_dep: 0, cash_paid: '', cash_payment_date: '', notes: '' });
const submit = () => form.post('/accounting/assets', { preserveScroll: true, onSuccess: () => { open.value = false; form.reset(); } });
</script>

<template>
    <Head title="Fixed assets" />
    <PageHeader title="Fixed assets" subtitle="Straight-line monthly depreciation, capped at cost − residual">
        <Button v-if="can('accounting.ledger.create')" icon="plus" class="hidden sm:inline-flex" @click="open = true">Register asset</Button>
    </PageHeader>
    <div class="space-y-4">
        <Card v-for="s in schedule" :key="s.asset.id" :title="s.asset.name" :subtitle="`${s.asset.code} · ${s.asset.category}`">
            <template #actions><Badge :label="s.asset.status" /></template>
            <dl class="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div><dt class="text-xs text-ink-3">Cost</dt><dd class="num font-semibold">{{ money(s.asset.cost) }}</dd></div>
                <div><dt class="text-xs text-ink-3">Monthly depreciation</dt><dd class="num font-semibold">{{ money(s.asset.monthly_depreciation) }}</dd></div>
                <div><dt class="text-xs text-ink-3">In service</dt><dd class="font-semibold">{{ date(s.asset.in_service_date) }}</dd></div>
                <div><dt class="text-xs text-ink-3">Life</dt><dd class="font-semibold">{{ s.asset.useful_life_months }} months</dd></div>
            </dl>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead class="text-[11px] uppercase tracking-wider text-ink-3"><tr><th class="py-1.5 text-left">Month</th><th class="py-1.5 text-right">Depreciation</th><th class="py-1.5 text-right">Net book value</th></tr></thead>
                    <tbody class="divide-y divide-line"><tr v-for="(m, k) in s.months" :key="k"><td class="py-1.5">{{ monthLabel(k) }}</td><td class="num py-1.5 text-right">{{ money(m.dep) }}</td><td class="num py-1.5 text-right font-medium">{{ money(m.nbv) }}</td></tr></tbody>
                </table>
            </div>
        </Card>
    </div>
    <BottomBar v-if="can('accounting.ledger.create')"><Button icon="plus" @click="open = true">Register asset</Button></BottomBar>
    <Drawer v-model="open" title="Register fixed asset">
        <form id="asset-form" class="space-y-4" @submit.prevent="submit">
            <Input v-model="form.name" label="Asset name" required :error="form.errors.name" />
            <Input v-model="form.category" label="Category" />
            <div class="grid grid-cols-2 gap-3"><Input v-model="form.acquisition_date" type="date" label="Acquired" :error="form.errors.acquisition_date" /><Input v-model="form.in_service_date" type="date" label="In service" :error="form.errors.in_service_date" /></div>
            <div class="grid grid-cols-2 gap-3"><MoneyInput v-model="form.cost" label="Cost" required :error="form.errors.cost" /><MoneyInput v-model="form.residual_value" label="Residual value" /></div>
            <div class="grid grid-cols-2 gap-3"><Input v-model="form.useful_life_months" type="number" min="1" label="Useful life (months)" :error="form.errors.useful_life_months" /><MoneyInput v-model="form.opening_accumulated_dep" label="Opening accum. dep." /></div>
            <div class="grid grid-cols-2 gap-3"><MoneyInput v-model="form.cash_paid" label="Cash paid now" hint="Direct purchase cash. Any unpaid part must be posted as a liability (e.g. LIA-AC)." /><Input v-model="form.cash_payment_date" type="date" label="Cash date" :error="form.errors.cash_payment_date" /></div>
            <Textarea v-model="form.notes" label="Notes" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="asset-form" :loading="form.processing">Register</Button></template>
    </Drawer>
</template>
