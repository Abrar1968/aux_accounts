<script setup>
defineProps({ label: String, type: { type: String, default: 'text' }, error: String, autocomplete: String });
const model = defineModel();
</script>

<template>
    <label class="block">
        <span class="mb-1.5 block text-sm font-medium">{{ label }}</span>
        <input v-model="model" :type="type" :autocomplete="autocomplete"
            :class="error ? 'border-negative' : 'border-line focus:border-brand-500'"
            class="h-10 w-full rounded-lg border bg-surface px-3 text-sm outline-none transition placeholder:text-ink-3" />
        <span v-if="error" class="mt-1 block text-xs text-negative">{{ error }}</span>
    </label>
</template>
