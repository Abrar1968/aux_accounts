<script setup>
defineProps({ label: String, error: String, hint: String, required: Boolean, for: String });
</script>

<template>
    <div class="min-w-0">
        <label v-if="label" :for="$props.for" class="mb-1.5 block text-[12.5px] font-semibold text-ink-2">{{ label }}<span v-if="required" class="text-negative"> *</span></label>
        <slot />
        <p v-if="error" class="mt-1.5 flex items-center gap-1 text-xs font-medium text-negative" role="alert">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-ink-3">{{ hint }}</p>
    </div>
</template>
