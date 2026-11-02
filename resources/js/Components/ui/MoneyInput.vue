<script setup>
import Input from './Input.vue';
defineProps({ label: String, error: String, hint: String, required: Boolean });
const model = defineModel();
</script>

<template>
    <Input v-model="model" type="number" step="0.01" min="0" inputmode="decimal" prefix="৳" class="num" :label="label" :error="error" :hint="hint" :required="required" />
</template>
