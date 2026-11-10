<script setup>
defineProps({ label: String, disabled: Boolean, description: String });
const model = defineModel({ type: Boolean });
</script>

<template>
    <label class="flex cursor-pointer items-start justify-between gap-4" :class="disabled && 'cursor-not-allowed opacity-60'">
        <span v-if="label" class="min-w-0"><span class="block text-sm font-medium">{{ label }}</span><span v-if="description" class="block text-xs text-ink-3">{{ description }}</span></span>
        <button type="button" role="switch" :aria-checked="model" :aria-label="label" :disabled="disabled" @click="model = !model"
            :class="model ? 'bg-brand-gradient shadow-[var(--shadow-brand)]' : 'bg-ink/15'" class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors">
            <span :class="model ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block size-5 rounded-full bg-white shadow transition-transform" />
        </button>
    </label>
</template>
