<script setup>
defineProps({ label: String, type: { type: String, default: 'text' }, error: String, autocomplete: String });
const model = defineModel();
</script>

<template>
    <label class="block">
        <span class="mb-1.5 block text-[13px] font-semibold text-ink">{{ label }}</span>
        <input v-model="model" :type="type" :autocomplete="autocomplete"
            :class="error && '!border-negative'" class="field !h-11 px-3.5" />
        <span v-if="error" class="mt-1 block text-xs text-negative">{{ error }}</span>
    </label>
</template>
