<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { money, pct, titleCase } from '@/lib/format';

const props = defineProps({ settings: Object, editable: Array, crm: Object });
const tab = ref('policies');
const LABELS = {
    'assets.ac_useful_life': ['AC useful life (months)', 'number'], 'assets.ac_residual': ['AC residual value (৳)', 'number'],
    'siam.start': ['Siam contract start', 'date'], 'siam.months': ['Siam contract months', 'number'], 'siam.share': ['Siam revenue share (0–1)', 'number'],
    'opening.other_fixed_assets': ['Other pre-April fixed assets (৳)', 'number'], 'opening.other_assets': ['Other opening assets / deposits (৳)', 'number'],
    'opening.cash': ['Cash before 1 Apr (৳)', 'number'], 'opening.other_liabilities': ['Other opening liabilities (৳)', 'number'],
    'crm.daily_slots': ['Daily lead target (1–500)', 'number'], 'crm.default_owner': ['Default lead owner', 'text'],
    'crm.default_business_type': ['Default business type', 'text'], 'crm.report_brand': ['Report brand', 'text'],
    'company.name': ['Company name (emails)', 'text'], 'company.website': ['Company website', 'text'], 'company.phone': ['Company phone', 'text'],
    'company.address': ['Company address', 'text'], 'company.signature_title': ['Email signature title', 'text'],
};
const fields = computed(() => props.editable.filter((k) => LABELS[k]));
const form = useForm({ settings: Object.fromEntries(fields.value.map((k) => [k, props.settings[k]])) });
const save = () => form.put('/settings', { preserveScroll: true });
const fixed = computed(() => Object.entries(props.settings).filter(([k]) => !props.editable.includes(k) && !k.startsWith('crm.cursor') && typeof props.settings[k] !== 'object'));
const option = useForm({ type: 'service', value: '' });
const addOption = () => option.post('/crm/settings/options', { preserveScroll: true, onSuccess: () => option.reset('value') });
const removeOption = (type, value) => router.delete('/crm/settings/options', { data: { type, value }, preserveScroll: true });
</script>

<template>
    <Head title="Settings" />
    <PageHeader title="Settings" subtitle="Accounting policies and CRM lists — statements recalculate automatically after a change" />
    <div class="mb-3"><Tabs v-model="tab" :tabs="[{ key: 'policies', label: 'Policies' }, { key: 'referrals', label: 'Referral tiers' }, { key: 'crm', label: 'CRM lists' }, { key: 'system', label: 'Model constants' }]" /></div>
    <Card v-if="tab === 'policies'" title="Editable policies">
        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
            <Input v-for="k in fields" :key="k" v-model="form.settings[k]" :type="LABELS[k][1]" :step="LABELS[k][1] === 'number' ? 'any' : undefined" :label="LABELS[k][0]" :error="form.errors[`settings.${k}`]" />
            <div class="sm:col-span-2"><Button type="submit" :loading="form.processing">Save policies</Button></div>
        </form>
    </Card>
    <Card v-else-if="tab === 'referrals'" title="Referral memo policy" subtitle="Applied to total contract value; expensed only when paid" :padded="false">
        <table class="w-full text-sm"><thead class="text-[11px] uppercase tracking-wider text-ink-3"><tr><th class="px-5 py-2 text-left">Contract value below</th><th class="px-5 py-2 text-right">Memo</th></tr></thead>
            <tbody class="divide-y divide-line"><tr v-for="(t, i) in settings['referral.tiers']" :key="i"><td class="num px-5 py-2">{{ t.below ? money(t.below) : 'and above' }}</td><td class="num px-5 py-2 text-right">{{ t.fixed !== undefined ? money(t.fixed) : pct(t.rate, 1) }}</td></tr></tbody>
        </table>
    </Card>
    <div v-else-if="tab === 'crm'" class="space-y-4">
        <Card title="Outcomes" subtitle="Outcome → stage, score and default follow-up days" :padded="false">
            <div class="overflow-x-auto"><table class="w-full min-w-[28rem] text-sm"><thead class="text-[11px] uppercase tracking-wider text-ink-3"><tr><th class="px-5 py-2 text-left">Outcome</th><th class="px-3 py-2 text-left">Stage</th><th class="px-3 py-2 text-right">Score</th><th class="px-5 py-2 text-right">Follow-up days</th></tr></thead>
                <tbody class="divide-y divide-line"><tr v-for="o in crm.outcomes" :key="o.id"><td class="px-5 py-2">{{ o.name }}</td><td class="px-3 py-2"><Badge :label="o.stage" /></td><td class="num px-3 py-2 text-right">{{ o.score }}</td><td class="num px-5 py-2 text-right">{{ o.followup_days }}</td></tr></tbody>
            </table></div>
        </Card>
        <Card title="Option lists">
            <form class="mb-4 flex flex-col gap-2 sm:flex-row" @submit.prevent="addOption">
                <select v-model="option.type" class="h-10 rounded-lg border border-line bg-surface px-3 text-sm" aria-label="List"><option v-for="t in Object.keys(crm.lists)" :key="t" :value="t">{{ titleCase(t) }}</option></select>
                <input v-model="option.value" class="h-10 flex-1 rounded-lg border border-line bg-surface px-3 text-sm" placeholder="New option" aria-label="Option" />
                <Button type="submit" :loading="option.processing">Add</Button>
            </form>
            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="(values, type) in crm.lists" :key="type"><p class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-3">{{ titleCase(type) }}</p>
                    <div class="flex flex-wrap gap-1.5"><span v-for="v in values" :key="v" class="inline-flex items-center gap-1 rounded-full bg-surface-2 py-0.5 pl-2.5 pr-1 text-xs">{{ v }}<button class="grid size-4 place-items-center rounded-full text-ink-3 hover:bg-line hover:text-ink" :aria-label="`Remove ${v}`" @click="removeOption(type, v)">×</button></span></div>
                </div>
            </div>
        </Card>
    </div>
    <Card v-else title="Model constants" subtitle="Fixed by the accounting model">
        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2"><template v-for="[k, v] in fixed" :key="k"><div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-ink-2">{{ k }}</dt><dd class="font-medium">{{ v }}</dd></div></template></dl>
    </Card>
</template>
