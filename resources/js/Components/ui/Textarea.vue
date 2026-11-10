<script setup>
import Field from './Field.vue';
defineOptions({ inheritAttrs: false });
defineProps({ label: String, error: String, hint: String, rows: { type: Number, default: 3 } });
const model = defineModel();
const id = `ta-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <Field :label="label" :error="error" :hint="hint" :for="id">
        <textarea :id="id" v-model="model" :rows="rows" v-bind="$attrs" :class="error && '!border-negative'" class="field !h-auto px-3 py-2.5 leading-relaxed" />
    </Field>
</template>
