<script setup>
// Upload receipts/evidence (images are resized with GD on the server). Lists existing files.
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { getJson } from '@/Composables/useJson';

const props = defineProps({ type: { type: String, required: true }, id: { type: Number, required: true }, files: { type: Array, default: () => [] } });
const drag = ref(false);
const busy = ref(false);
function upload(fileList) {
    const file = fileList?.[0];
    if (!file) return;
    busy.value = true;
    router.post('/attachments', { attachable_type: props.type, attachable_id: props.id, file }, { forceFormData: true, preserveScroll: true, onFinish: () => (busy.value = false) });
}
async function open(f) { const { url } = await getJson(`/attachments/${f.id}/link`); window.open(url, '_blank', 'noopener'); }
</script>

<template>
    <div class="space-y-3">
        <label @dragover.prevent="drag = true" @dragleave="drag = false" @drop.prevent="drag = false; upload($event.dataTransfer.files)"
            :class="drag ? 'border-brand-500 bg-brand-50' : 'border-line'" class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border border-dashed px-4 py-6 text-center text-sm transition">
            <Icon name="upload" class="text-ink-3" /><span class="font-medium">{{ busy ? 'Uploading…' : 'Drop a receipt or click to upload' }}</span>
            <span class="text-xs text-ink-3">JPEG, PNG, WebP or PDF · max 10 MB</span>
            <input type="file" class="sr-only" accept="image/*,application/pdf" @change="upload($event.target.files)" />
        </label>
        <ul v-if="files.length" class="divide-y divide-line rounded-lg border border-line">
            <li v-for="f in files" :key="f.id" class="flex items-center gap-3 px-3 py-2 text-sm">
                <Icon :name="f.mime === 'application/pdf' ? 'doc' : 'box'" class="text-ink-3" />
                <button class="min-w-0 flex-1 truncate text-left font-medium hover:underline" @click="open(f)">{{ f.original_name }}</button>
                <span class="text-xs text-ink-3">{{ Math.round(f.size / 1024) }} KB</span>
            </li>
        </ul>
    </div>
</template>
