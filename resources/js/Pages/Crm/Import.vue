<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Icon from '@/Components/ui/Icon.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';

const props = defineProps({ columns: Array, preview: Object });
const upload = useForm({ file: null });
const send = (files) => { upload.file = files?.[0]; if (upload.file) upload.post('/crm/import/preview', { forceFormData: true }); };
const tab = ref('all');
const selected = ref(new Set());
watch(() => props.preview, (p) => { selected.value = new Set((p?.rows ?? []).filter((r) => r.status === 'new').map((r) => r.line)); tab.value = 'all'; }, { immediate: true });
const rows = computed(() => (props.preview?.rows ?? []).filter((r) => tab.value === 'all' || r.status === tab.value));
const toggle = (r) => { if (r.status !== 'new') return; const s = new Set(selected.value); s.has(r.line) ? s.delete(r.line) : s.add(r.line); selected.value = s; };
const STATUS = { new: ['New', 'positive'], duplicate_crm: ['Already in CRM', 'warning'], duplicate_file: ['Duplicate in file', 'warning'], invalid: ['Invalid', 'negative'] };
const committing = ref(false);
const commit = () => { committing.value = true; router.post('/crm/import/commit', { token: props.preview.token, lines: [...selected.value] }, { onFinish: () => (committing.value = false) }); };
</script>

<template>
    <Head title="Import leads" />
    <PageHeader title="Import leads from CSV" subtitle="Preview first — duplicates (by normalised phone) and invalid numbers are never imported">
        <a href="/crm/import/template" download class="inline-flex h-8 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-xs font-medium hover:bg-surface-2"><Icon name="download" :size="14" />CSV template</a>
    </PageHeader>
    <Card v-if="!preview" class="max-w-2xl">
        <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed border-line px-4 py-10 text-center" @dragover.prevent @drop.prevent="send($event.dataTransfer.files)">
            <Icon name="upload" :size="22" class="text-ink-3" />
            <span class="font-medium">{{ upload.processing ? 'Reading file…' : 'Drop a CSV file or click to choose' }}</span>
            <span class="text-xs text-ink-3">UTF-8 CSV, first row = column names, up to 2,000 rows · 2 MB</span>
            <input type="file" accept=".csv,text/csv" class="sr-only" @change="send($event.target.files)" />
        </label>
        <p v-if="upload.errors.file" class="mt-2 text-sm text-negative">{{ upload.errors.file }}</p>
        <p class="mt-4 text-sm text-ink-2">Columns: <code class="rounded bg-surface-2 px-1 text-xs">{{ columns.join(', ') }}</code>. Only <strong>phone</strong> is required; Bangladesh numbers (01…) and +international numbers are accepted.</p>
    </Card>
    <template v-else>
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card p-3"><p class="text-xs text-ink-3">Ready to import</p><p class="num text-xl font-semibold text-positive">{{ preview.counts.new }}</p></div>
            <div class="card p-3"><p class="text-xs text-ink-3">Already in CRM</p><p class="num text-xl font-semibold text-warning">{{ preview.counts.duplicate_crm }}</p></div>
            <div class="card p-3"><p class="text-xs text-ink-3">Duplicate in file</p><p class="num text-xl font-semibold text-warning">{{ preview.counts.duplicate_file }}</p></div>
            <div class="card p-3"><p class="text-xs text-ink-3">Invalid</p><p class="num text-xl font-semibold text-negative">{{ preview.counts.invalid }}</p></div>
        </div>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <Tabs v-model="tab" :tabs="[{ key: 'all', label: 'All', count: preview.counts.total }, { key: 'new', label: 'New', count: preview.counts.new }, { key: 'duplicate_crm', label: 'In CRM', count: preview.counts.duplicate_crm }, { key: 'duplicate_file', label: 'In file', count: preview.counts.duplicate_file }, { key: 'invalid', label: 'Invalid', count: preview.counts.invalid }]" />
            <div class="hidden gap-2 sm:flex"><Button variant="secondary" @click="router.get('/crm/import')">Start over</Button><Button :disabled="!selected.size" :loading="committing" @click="commit">Import {{ selected.size }} lead(s)</Button></div>
        </div>
        <Card :padded="false">
            <ul class="divide-y divide-line">
                <li v-for="r in rows" :key="r.line" class="flex items-start gap-3 px-4 py-3 sm:px-5" :class="r.status === 'new' && 'cursor-pointer hover:bg-surface-2/60'" @click="toggle(r)">
                    <input type="checkbox" class="mt-1 size-4 rounded border-line" :checked="selected.has(r.line)" :disabled="r.status !== 'new'" :aria-label="`Import row ${r.line}`" @click.stop="toggle(r)" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2"><span class="num font-medium">{{ r.data.phone || '—' }}</span><Badge :tone="STATUS[r.status][1]" :label="STATUS[r.status][0]" /><span class="text-xs text-ink-3">row {{ r.line }}</span></div>
                        <p class="truncate text-xs text-ink-2">{{ [r.data.business_type, r.data.service, r.data.source, r.data.outcome, r.data.owner].filter(Boolean).join(' · ') || 'No extra fields' }}</p>
                        <p v-if="r.issues.length" class="text-xs text-warning">{{ r.issues.join(' · ') }}</p>
                    </div>
                </li>
            </ul>
        </Card>
        <BottomBar><Button variant="secondary" @click="router.get('/crm/import')">Start over</Button><Button :disabled="!selected.size" :loading="committing" @click="commit">Import {{ selected.size }}</Button></BottomBar>
    </template>
</template>
