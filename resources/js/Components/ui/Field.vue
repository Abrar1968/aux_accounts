<script setup>
defineProps({ label: String, error: String, hint: String, required: Boolean, for: String });
</script>

<template>
    <div class="min-w-0">
        <label v-if="label" :for="$props.for" class="mb-1.5 block text-sm font-medium text-ink">{{ label }}<span v-if="required" class="text-negative"> *</span></label>
        <slot />
        <p v-if="error" class="mt-1 text-xs text-negative" role="alert">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-xs text-ink-3">{{ hint }}</p>
    </div>
</template>
