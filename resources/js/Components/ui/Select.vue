<script setup>
import Field from './Field.vue';
defineOptions({ inheritAttrs: false });
const props = defineProps({ label: String, error: String, hint: String, required: Boolean, options: { type: Array, default: () => [] }, placeholder: String });
const model = defineModel();
const id = `sel-${Math.random().toString(36).slice(2, 9)}`;
const opt = (o) => (typeof o === 'object' ? o : { value: o, label: o });
</script>

<template>
    <Field :label="label" :error="error" :hint="hint" :required="required" :for="id">
        <select :id="id" v-model="model" v-bind="$attrs" :class="error && '!border-negative'"
            class="field appearance-none bg-[length:16px] bg-[right_10px_center] bg-no-repeat pl-3 pr-9"
            style="background-image:url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E&quot;)">
            <option v-if="placeholder !== undefined" value="">{{ placeholder }}</option>
            <option v-for="o in props.options.map(opt)" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
    </Field>
</template>
