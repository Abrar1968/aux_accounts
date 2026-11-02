<script setup>
import Icon from './Icon.vue';
defineProps({ placeholder: { type: String, default: 'Search…' } });
const q = defineModel('q');
</script>

<template>
    <div class="no-print flex flex-col gap-2 border-b border-line p-3 sm:flex-row sm:items-center sm:px-4">
        <label class="relative flex-1 sm:max-w-xs">
            <span class="sr-only">Search</span>
            <Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
            <input v-model="q" type="search" :placeholder="placeholder" class="h-9 w-full rounded-lg border border-line bg-surface pl-9 pr-3 text-sm outline-none focus:border-brand-500" />
        </label>
        <div class="flex flex-wrap items-center gap-2 [&_select]:h-9"><slot /></div>
    </div>
</template>
