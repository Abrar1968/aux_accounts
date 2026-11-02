<script setup>
import Icon from './Icon.vue';
defineProps({ placeholder: { type: String, default: 'Search…' }, count: [Number, String] });
const q = defineModel('q');
</script>

<template>
    <div class="no-print flex flex-col gap-2 border-b border-line p-3 sm:flex-row sm:items-center sm:px-4">
        <label class="relative flex-1 sm:max-w-sm">
            <span class="sr-only">Search</span>
            <Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
            <input v-model="q" type="search" :placeholder="placeholder" class="field !h-9 pl-9 pr-3" />
        </label>
        <div class="flex flex-wrap items-center gap-2 [&_select]:field [&_select]:!h-9 [&_select]:!w-auto [&_select]:pl-3 [&_select]:pr-8"><slot /></div>
        <p v-if="count !== undefined" class="num ml-auto hidden text-xs font-medium text-ink-3 sm:block">{{ count }} records</p>
    </div>
</template>
