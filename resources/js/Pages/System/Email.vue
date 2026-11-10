<script setup>
// Admin email composer: pick a template + record → professional email auto-generated from live
// figures; tick the details to include, edit the wording, preview exactly what is sent, send via SMTP.
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import Switch from '@/Components/ui/Switch.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Modal from '@/Components/ui/Modal.vue';
import Icon from '@/Components/ui/Icon.vue';
import RecipientPicker from '@/Components/ui/RecipientPicker.vue';
import { getJson, postForHtml } from '@/Composables/useJson';
import { dateTime } from '@/lib/format';

const props = defineProps({ templates: Array, history: Object, contacts: { type: Array, default: () => [] }, from: Object, draft: Object, filters: Object });

const form = useForm({
    template: '', record_ref: null, to: '', cc: '', subject: '', greeting: 'Hello,', intro: '', closing: '',
    details: [], include_table: false, table: null,
});
const record = ref(null); // combobox option
const recordKey = ref(0); // remount combobox on template change
const loading = ref(false);
const current = computed(() => props.templates.find((t) => t.key === form.template));
const templateLabel = (k) => props.templates.find((t) => t.key === k)?.label ?? k;

function chooseTemplate(t) {
    form.template = t.key;
    form.record_ref = null;
    record.value = null;
    recordKey.value++;
    form.clearErrors();
    if (!t.source) loadDraft();
}
const fetchRecords = (q) => getJson('/system/email/records', { template: form.template, q });
watch(record, (o) => { if (o) { form.record_ref = o.value; loadDraft(); } });

function loadDraft() {
    loading.value = true;
    router.reload({
        only: ['draft'], data: { template: form.template, record: form.record_ref ?? undefined },
        preserveState: true, preserveScroll: true, onFinish: () => (loading.value = false),
    });
}
// server draft → editable form (recipients are kept)
watch(() => props.draft, (d) => {
    if (!d) return;
    Object.assign(form, {
        template: d.template, record_ref: d.record_ref, subject: d.subject, greeting: d.greeting, intro: d.intro, closing: d.closing,
        details: d.details.map((x) => ({ ...x })), table: d.table, include_table: d.include_table,
    });
    suggested.value = d.recipients ?? [];
    if (!form.to && suggested.value.length) form.to = suggested.value.join(', '); // the record's client, ready to send

}, { immediate: true });

const suggested = ref([]);
const addDetail = () => form.details.push({ label: '', value: '', on: true });
const removeDetail = (i) => form.details.splice(i, 1);
const recipients = computed(() => form.to.split(/[,;\s]+/).filter(Boolean).map((e) => e.toLowerCase()));
function addRecipient(email) {
    if (!recipients.value.includes(email.toLowerCase())) form.to = [...recipients.value, email.toLowerCase()].join(', ');
}

// Live preview — rendered by the same Blade view that is emailed.
const previewHtml = ref('');
const previewOpen = ref(false);
let timer;
const payload = () => ({ ...form.data() });
async function refreshPreview() {
    if (!form.template) return;
    try { previewHtml.value = await postForHtml('/system/email/preview', payload()); } catch { /* invalid draft while typing */ }
}
watch(() => JSON.stringify(payload()), () => { clearTimeout(timer); timer = setTimeout(refreshPreview, 450); });
onBeforeUnmount(() => clearTimeout(timer));

const send = () => form.post('/system/email', {
    preserveScroll: true,
    onSuccess: () => { if (!Object.keys(form.errors).length) form.reset('to', 'cc'); },
});
const ready = computed(() => form.template && form.intro && recipients.value.length && (!current.value?.source || form.record_ref));
const search = ref(props.filters?.q ?? '');
watch(search, (q) => { clearTimeout(timer); timer = setTimeout(() => router.reload({ only: ['history'], data: { q: q || undefined }, preserveState: true }), 300); });
</script>

<template>
    <Head title="Send email" />
    <PageHeader title="Send email" :subtitle="`Professional emails generated from live records · sent from ${from.address}`" />

    <!-- 1. template -->
    <Card title="1 · Choose what to send" class="mb-4">
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            <button v-for="t in templates" :key="t.key" type="button" :aria-pressed="form.template === t.key" @click="chooseTemplate(t)"
                :class="form.template === t.key ? 'border-brand-400 bg-brand-50 ring-1 ring-brand-400' : 'border-line hover:-translate-y-0.5 hover:border-brand-400/50 hover:bg-ink/[0.03]'"
                class="rounded-2xl border p-3 text-left transition-all duration-200">
                <p class="text-sm font-semibold">{{ t.label }}</p>
                <p class="mt-0.5 text-xs text-ink-3">{{ t.description }}</p>
            </button>
        </div>
    </Card>

    <div v-if="form.template" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <form class="min-w-0 space-y-4" @submit.prevent="send">
            <Card title="2 · Record & recipients">
                <div class="space-y-3">
                    <Combobox v-if="current?.source" :key="recordKey" v-model="record" :fetcher="fetchRecords" label="Record"
                        :placeholder="{ payment: 'Search receipt, customer or revenue ID…', receivable: 'Search customer account…', project: 'Search project…', payroll: 'Search employee or month…', lead: 'Search phone or business…', month: 'Pick a month…' }[current.source]"
                        :error="form.errors.record_ref" />
                    <p v-if="loading" class="text-xs text-ink-3">Generating email from the latest figures…</p>
                    <RecipientPicker v-model="form.to" :contacts="contacts" label="To" :error="form.errors.to"
                        :hint="`Search ${contacts.length} saved addresses (every project client, the team, past recipients) or type any email · Enter / comma to add`" />
                    <div v-if="suggested.some((e) => !recipients.includes(e))" class="flex flex-wrap items-center gap-1.5">
                        <span class="eyebrow text-ink-3">For this record</span>
                        <button v-for="e in suggested.filter((x) => !recipients.includes(x))" :key="e" type="button" class="rounded-full border border-brand-400/40 bg-brand-50 px-2.5 py-1 text-xs font-medium text-ink transition hover:-translate-y-px hover:border-brand-400" @click="addRecipient(e)">+ {{ e }}</button>
                    </div>
                    <RecipientPicker v-model="form.cc" :contacts="contacts" label="CC (optional)" :error="form.errors.cc" placeholder="Copy someone in…" />
                </div>
            </Card>

            <Card title="3 · Message" subtitle="Auto-generated — edit anything before sending">
                <div class="space-y-3">
                    <Input v-model="form.subject" label="Subject" :error="form.errors.subject" />
                    <Input v-model="form.greeting" label="Greeting" :error="form.errors.greeting" />
                    <Textarea v-model="form.intro" :rows="4" label="Message" :error="form.errors.intro" />

                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <p class="text-sm font-medium">Details to include</p>
                            <button type="button" class="text-sm font-medium text-brand-600 hover:underline" @click="addDetail">+ Add line</button>
                        </div>
                        <p v-if="!form.details.length" class="rounded-lg border border-dashed border-line p-3 text-xs text-ink-3">No detail lines. Add your own, e.g. “Invoice number”.</p>
                        <ul class="divide-y divide-line rounded-lg border border-line">
                            <li v-for="(d, i) in form.details" :key="i" class="flex items-center gap-2 px-2.5 py-2">
                                <input v-model="d.on" type="checkbox" class="size-4 shrink-0 accent-brand-600" :aria-label="`Include ${d.label || 'line'}`" />
                                <input v-model="d.label" class="h-8 min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-1.5 text-sm text-ink-2 outline-none hover:border-line focus:border-brand-500" placeholder="Label" aria-label="Detail label" />
                                <input v-model="d.value" class="num h-8 min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-1.5 text-right text-sm font-medium outline-none hover:border-line focus:border-brand-500" placeholder="Value" aria-label="Detail value" />
                                <button type="button" class="rounded p-1 text-ink-3 hover:bg-surface-2 hover:text-negative" :aria-label="`Remove ${d.label || 'line'}`" @click="removeDetail(i)"><Icon name="x" :size="14" /></button>
                            </li>
                        </ul>
                    </div>

                    <Switch v-if="form.table" v-model="form.include_table" label="Include transaction table" :description="`${form.table.rows.length} line(s): every charge and payment with running balance`" />
                    <Textarea v-model="form.closing" :rows="2" label="Closing" :error="form.errors.closing" />
                </div>
            </Card>

            <div class="flex flex-wrap items-center gap-2">
                <Button type="submit" icon="send" :loading="form.processing" :disabled="!ready">Send{{ recipients.length ? ` to ${recipients.length} recipient${recipients.length > 1 ? 's' : ''}` : '' }}</Button>
                <Button type="button" variant="secondary" class="lg:hidden" @click="refreshPreview(); previewOpen = true">Preview</Button>
                <p class="text-xs text-ink-3">Replies go to {{ from.address }}</p>
            </div>
        </form>

        <!-- live preview (desktop) -->
        <div class="hidden min-w-0 lg:block">
            <div class="card sticky top-20 overflow-hidden">
                <div class="flex items-center justify-between border-b border-line px-4 py-2.5">
                    <p class="text-sm font-semibold">Preview</p><p class="truncate pl-3 text-xs text-ink-3">{{ form.subject }}</p>
                </div>
                <iframe :srcdoc="previewHtml" title="Email preview" sandbox="" class="h-[72vh] w-full bg-white"></iframe>
            </div>
        </div>
    </div>

    <Modal v-model="previewOpen" title="Email preview" max-width="max-w-2xl">
        <iframe :srcdoc="previewHtml" title="Email preview" sandbox="" class="h-[70vh] w-full rounded-lg bg-white"></iframe>
    </Modal>

    <Card :padded="false" title="Sent emails" class="mt-6">
        <div class="border-b border-line p-3"><label class="relative block sm:max-w-sm"><Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" /><input v-model="search" type="search" data-page-search placeholder="Search recipient, subject or template…" aria-label="Search sent emails" class="field !h-9 !rounded-full pl-9 pr-3" @keydown.esc="search = ''" /></label></div>
        <DataTable :rows="history.data" empty="No emails sent yet."
            :columns="[{ key: 'subject', label: 'Subject', primary: true }, { key: 'status', label: 'Status', secondary: true }, { key: 'to', label: 'To' }, { key: 'template', label: 'Template', format: templateLabel }, { key: 'user', label: 'By', format: (v) => v?.name ?? '—' }, { key: 'created_at', label: 'When', format: dateTime }]">
            <template #cell-status="{ row }"><Badge :label="row.status === 'sent' ? 'Sent' : row.status === 'failed' ? 'Failed' : 'Sending'" :tone="row.status === 'sent' ? 'positive' : row.status === 'failed' ? 'negative' : 'warning'" :title="row.error || undefined" /></template>
        </DataTable>
        <div class="p-3"><Pagination :page="history" /></div>
    </Card>
</template>
