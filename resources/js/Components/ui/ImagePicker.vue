<script setup>
// Pick any image (JPG/PNG/WebP/GIF/BMP/AVIF/SVG/HEIC…) with a preview. Formats the server's GD
// cannot read are converted to PNG in the browser (lib/images.js). v-model = File | null.
import { onBeforeUnmount, ref, watch } from 'vue';
import Field from './Field.vue';
import Icon from './Icon.vue';
import { IMAGE_ACCEPT, normalizeImage } from '@/lib/images';

const props = defineProps({ label: String, hint: String, error: String, current: String, height: { type: String, default: 'h-24' } });
const model = defineModel();
const id = `img-${Math.random().toString(36).slice(2, 9)}`;
const preview = ref(null);
const busy = ref(false);
const localError = ref('');
let objectUrl = null;
const revoke = () => objectUrl && URL.revokeObjectURL(objectUrl);
watch(model, (f) => { if (!f) { revoke(); preview.value = null; } });
onBeforeUnmount(revoke);

async function pick(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    busy.value = true; localError.value = '';
    try {
        const ready = await normalizeImage(file);
        revoke(); objectUrl = URL.createObjectURL(ready); preview.value = objectUrl;
        model.value = ready;
    } catch (err) { localError.value = err.message; } finally { busy.value = false; }
}
</script>

<template>
    <Field :label="label" :hint="hint" :error="error || localError" :for="id">
        <label :for="id" class="flex cursor-pointer items-center gap-4 rounded-xl border border-dashed border-line p-3 transition hover:border-brand-500">
            <div :class="height" class="grid w-40 shrink-0 place-items-center overflow-hidden rounded-lg bg-[repeating-conic-gradient(#f3f4f6_0%_25%,#fff_0%_50%)] bg-[length:16px_16px]">
                <img v-if="preview || current" :src="preview || current" alt="" class="max-h-full max-w-full object-contain" />
                <Icon v-else name="upload" class="text-ink-3" />
            </div>
            <div class="min-w-0 text-sm">
                <p class="font-medium">{{ busy ? 'Preparing image…' : preview ? 'Ready to save' : current ? 'Click to replace' : 'Click to choose an image' }}</p>
                <p class="text-xs text-ink-3">JPG, PNG, WebP, GIF, BMP, AVIF, SVG, HEIC… · max 10 MB · transparent PNG looks best</p>
            </div>
            <input :id="id" type="file" class="sr-only" :accept="IMAGE_ACCEPT" @change="pick" />
        </label>
    </Field>
</template>
