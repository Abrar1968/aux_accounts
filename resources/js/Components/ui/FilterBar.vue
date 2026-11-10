<script setup>
// List search + filters. Words match in any order (server side, App\Support\Search); Ctrl+F focuses it,
// Esc clears it, a spinner shows while the list refreshes.
import { onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { keyLabel } from '@/lib/shortcuts';
defineProps({ placeholder: { type: String, default: 'Search…' }, count: [Number, String] });
const q = defineModel('q');
const busy = ref(false);
const offs = [router.on('start', () => (busy.value = true)), router.on('finish', () => (busy.value = false))];
onBeforeUnmount(() => offs.forEach((off) => off()));
</script>

<template>
    <div class="no-print flex flex-col gap-2 border-b border-line p-3 sm:flex-row sm:items-center sm:px-4">
        <label class="relative flex-1 sm:max-w-sm">
            <span class="sr-only">Search</span>
            <Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
            <input v-model="q" type="search" data-page-search :placeholder="placeholder" class="field !h-9 !rounded-full pl-9 pr-16" @keydown.esc="q = ''" />
            <span v-if="busy && q" class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-brand-400 border-t-transparent" aria-hidden="true" />
            <kbd v-else-if="!q" class="pointer-events-none absolute right-3 top-1/2 hidden -translate-y-1/2 rounded border border-line px-1 font-mono text-[10px] text-ink-3 lg:block">{{ keyLabel('Ctrl') }}+F</kbd>
        </label>
        <div class="flex flex-wrap items-center gap-2 [&_select]:field [&_select]:!h-9 [&_select]:!w-auto [&_select]:pl-3 [&_select]:pr-8"><slot /></div>
        <p v-if="count !== undefined" class="eyebrow ml-auto hidden text-ink-3 sm:block">{{ count }} records</p>
    </div>
</template>
