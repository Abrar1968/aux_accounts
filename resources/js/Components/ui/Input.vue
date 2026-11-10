<script setup>
import Field from './Field.vue';
defineOptions({ inheritAttrs: false });
defineProps({ label: String, error: String, hint: String, required: Boolean, type: { type: String, default: 'text' }, prefix: String });
const model = defineModel();
const id = `in-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <Field :label="label" :error="error" :hint="hint" :required="required" :for="id">
        <div class="relative">
            <span v-if="prefix" class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-medium text-ink-3">{{ prefix }}</span>
            <input :id="id" v-model="model" :type="type" v-bind="$attrs" :aria-invalid="!!error" :class="[prefix ? 'pl-8' : 'pl-3', error && '!border-negative']" class="field pr-3" />
        </div>
    </Field>
</template>
