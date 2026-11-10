<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import ImagePicker from '@/Components/ui/ImagePicker.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { dateTime, money, pct, titleCase } from '@/lib/format';

const props = defineProps({ settings: Object, editable: Array, crm: Object, sheets: Object });
const sheetStatus = computed(() => props.sheets?.status ?? {});
const sheetHealthy = computed(() => sheetStatus.value.last_seen && (Date.now() - new Date(sheetStatus.value.last_seen.replace(' ', 'T') + '+06:00').getTime()) < 15 * 60 * 1000);
const rotating = ref(false);
const rotateSecret = () => { rotating.value = true; router.post('/settings/integrations/google-sheets/secret', {}, { preserveScroll: true, onFinish: () => (rotating.value = false) }); };
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
// Branding: logo + accounts seal used on every invoice / PDF / email
const brand = computed(() => usePage().props.brand ?? {});
const brandForms = { logo: useForm({ asset: 'logo', image: null }), seal: useForm({ asset: 'seal', image: null }) };
const saveBrand = (k) => brandForms[k].post('/settings/branding', { forceFormData: true, preserveScroll: true, onSuccess: () => brandForms[k].reset('image') });
const accents = [
    { key: 'indigo', label: 'Indigo', from: '#6366f1', to: '#8b5cf6' }, { key: 'blue', label: 'Ocean', from: '#3b82f6', to: '#06b6d4' },
    { key: 'violet', label: 'Violet', from: '#8b5cf6', to: '#d946ef' }, { key: 'emerald', label: 'Emerald', from: '#10b981', to: '#14b8a6' },
    { key: 'teal', label: 'Teal', from: '#14b8a6', to: '#06b6d4' }, { key: 'rose', label: 'Rose', from: '#f43f5e', to: '#fb923c' },
    { key: 'amber', label: 'Amber', from: '#f59e0b', to: '#f97316' },
];
const setAccent = (accent) => { document.documentElement.dataset.accent = accent; router.put('/settings/branding/accent', { accent }, { preserveScroll: true }); };
const removeBrand = (k) => router.delete(`/settings/branding/${k}`, { preserveScroll: true });
const option = useForm({ type: 'service', value: '' });
const addOption = () => option.post('/crm/settings/options', { preserveScroll: true, onSuccess: () => option.reset('value') });
const removeOption = (type, value) => router.delete('/crm/settings/options', { data: { type, value }, preserveScroll: true });
</script>

<template>
    <Head title="Settings" />
    <PageHeader title="Settings" subtitle="Accounting policies and CRM lists — statements recalculate automatically after a change" />
    <div class="mb-3"><Tabs v-model="tab" :tabs="[{ key: 'policies', label: 'Policies' }, { key: 'branding', label: 'Branding' }, { key: 'integrations', label: 'Integrations' }, { key: 'referrals', label: 'Referral tiers' }, { key: 'crm', label: 'CRM lists' }, { key: 'system', label: 'Model constants' }]" /></div>
    <div v-if="tab === 'integrations'" class="grid gap-4 xl:grid-cols-3">
        <Card title="Google Sheets → Lead CRM" subtitle="Your daily lead sheet updates the CRM automatically" icon="layers" class="xl:col-span-2">
            <div class="flex flex-wrap items-center gap-3">
                <Badge v-if="!sheets.enabled" tone="neutral" label="Not enabled" />
                <Badge v-else-if="sheetHealthy" tone="positive" label="Connected" />
                <Badge v-else-if="sheetStatus.last_seen" tone="warning" label="No sync in the last 15 min" />
                <Badge v-else tone="warning" label="Waiting for the sheet" />
                <span v-if="sheetStatus.last_seen" class="text-sm text-ink-2">Last sync {{ dateTime(sheetStatus.last_seen) }} · last change {{ sheetStatus.last_change ? dateTime(sheetStatus.last_change) : '—' }}</span>
            </div>
            <dl v-if="sheetStatus.summary" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="[k, l] in [['created', 'New leads'], ['updated', 'Updated'], ['unchanged', 'Unchanged'], ['calls_added', 'Calls added']]" :key="k" class="rounded-xl border border-line bg-surface-2/50 p-3">
                    <dt class="text-xs text-ink-3">{{ l }} <span class="text-[10px]">({{ sheetStatus.mode === 'full' ? '5-min sync' : 'last edit' }})</span></dt><dd class="num text-xl font-bold">{{ sheetStatus.summary[k] ?? 0 }}</dd>
                </div>
            </dl>
            <div v-if="sheetStatus.errors?.length" class="mt-4 rounded-xl border border-warning/30 bg-warning/10 p-3 text-sm">
                <p class="font-semibold text-warning">Rows that need fixing in the sheet</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5 text-ink-2"><li v-for="e in sheetStatus.errors" :key="e">{{ e }}</li></ul>
            </div>
            <ol class="mt-5 space-y-2.5 text-sm text-ink-2">
                <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-600 dark:text-brand-300">1</span><span><b class="text-ink">{{ sheets.enabled ? 'Sync is enabled.' : 'Enable the sync' }}</b> — this creates a private secret that only your sheet knows.</span></li>
                <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-600 dark:text-brand-300">2</span><span><b class="text-ink">Download the script</b> (your site address and secret are already filled in).</span></li>
                <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-600 dark:text-brand-300">3</span><span>In the lead sheet open <b class="text-ink">Extensions → Apps Script</b>, add a new script file, paste it and save.</span></li>
                <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-600 dark:text-brand-300">4</span><span>Select <b class="text-ink">auxtechSetup</b> and press <b class="text-ink">Run</b>, then allow access. Edits sync instantly; a full re-sync runs every 5 minutes.</span></li>
            </ol>
            <div class="mt-5 flex flex-wrap gap-2">
                <Button v-if="!sheets.enabled" icon="bolt" :loading="rotating" @click="rotateSecret">Enable sync</Button>
                <template v-else>
                    <a href="/settings/integrations/google-sheets/script" download class="inline-flex h-10 items-center gap-2 rounded-[10px] bg-brand-gradient px-4 text-sm font-semibold text-white shadow-[var(--shadow-brand)] ring-1 ring-inset ring-white/15 hover:brightness-110"><Icon name="download" :size="16" />Download Apps Script</a>
                    <Button variant="secondary" icon="reverse" :loading="rotating" @click="rotateSecret">Rotate secret</Button>
                </template>
            </div>
        </Card>
        <Card title="How it syncs" icon="info">
            <ul class="space-y-2.5 text-sm text-ink-2">
                <li><b class="text-ink">Leads</b> are matched by Lead ID, then by phone. New rows become new leads; changed rows update stage, score and follow-ups.</li>
                <li><b class="text-ink">Call Log</b> rows are added once each, by Call ID.</li>
                <li>The sheet is the source of truth for the columns it holds; an edit in the sheet overwrites the same fields in the CRM.</li>
                <li>Nothing is deleted: leads archived in the CRM stay archived, and removed sheet rows are left alone.</li>
                <li class="break-all text-xs text-ink-3">Endpoint: {{ sheets.endpoint }}</li>
            </ul>
        </Card>
    </div>
    <div v-if="tab === 'branding'" class="grid gap-4 lg:grid-cols-2">
        <Card title="Accent colour" subtitle="Buttons, highlights, charts and the sidebar glow — applies to everyone instantly" icon="palette" class="lg:col-span-2">
            <div class="flex flex-wrap gap-3">
                <button v-for="a in accents" :key="a.key" type="button" :aria-pressed="brand.accent === a.key" @click="setAccent(a.key)"
                    :class="brand.accent === a.key ? 'border-ink ring-2 ring-ink/10' : 'border-line hover:border-line-strong'"
                    class="flex items-center gap-2.5 rounded-xl border bg-surface py-2 pl-2 pr-3.5 text-sm font-semibold shadow-[var(--shadow-xs)] transition">
                    <span class="size-7 rounded-lg shadow-inner" :style="{ background: `linear-gradient(135deg, ${a.from}, ${a.to})` }" />{{ a.label }}
                    <Icon v-if="brand.accent === a.key" name="check" :size="16" class="text-positive" />
                </button>
            </div>
        </Card>
        <Card v-for="k in ['logo', 'seal']" :key="k" :title="k === 'logo' ? 'Company logo' : 'Accounts seal / signature'"
            :subtitle="k === 'logo' ? 'Letterhead of every invoice, statement, payslip, report and email' : 'Printed as the authorised signature on every invoice and PDF'">
            <ImagePicker v-model="brandForms[k].image" :current="brand[k]?.png" :label="k === 'logo' ? 'Logo image' : 'Seal / signature image'" :error="brandForms[k].errors.image" />
            <div class="mt-3 flex gap-2">
                <Button size="sm" :disabled="!brandForms[k].image" :loading="brandForms[k].processing" @click="saveBrand(k)">Save</Button>
                <Button v-if="brand[k] && !brand[k].default" size="sm" variant="ghost" @click="removeBrand(k)">Remove</Button><span v-else-if="brand[k]?.default" class="self-center text-xs text-ink-3">Built-in company default — upload to replace</span>
            </div>
        </Card>
        <p class="text-xs text-ink-3 lg:col-span-2">Company name, address, phone, website and signature title are edited under <b>Policies</b>. Client signatures are added on each project.</p>
    </div>
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
