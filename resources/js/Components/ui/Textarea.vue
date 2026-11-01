<script setup>
import Field from './Field.vue';
defineOptions({ inheritAttrs: false });
defineProps({ label: String, error: String, hint: String, rows: { type: Number, default: 3 } });
const model = defineModel();
</script>

<template>
    <Field :label="label" :error="error" :hint="hint">
        <textarea v-model="model" :rows="rows" v-bind="$attrs" :class="error ? 'border-negative' : 'border-line focus:border-brand-500'"
            class="w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink outline-none placeholder:text-ink-3" />
    </Field>
</template>
