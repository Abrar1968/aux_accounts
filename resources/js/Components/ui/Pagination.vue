<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
// Accepts a Laravel paginator ({current_page,...}) or a resource collection ({data, meta}).
const props = defineProps({ page: Object });
const meta = computed(() => props.page?.meta ?? props.page ?? {});
const links = computed(() => (meta.value.links ?? []).map((l) => ({ ...l, label: l.label.replace('&laquo; ', '‹ ').replace(' &raquo;', ' ›') })));
</script>

<template>
    <div v-if="meta.last_page > 1" class="no-print flex flex-col items-center justify-between gap-3 border-t border-line px-4 py-3 text-sm sm:flex-row">
        <p class="text-ink-3">{{ meta.from }}–{{ meta.to }} of {{ meta.total }}</p>
        <nav class="flex max-w-full flex-wrap justify-center gap-1" aria-label="Pagination">
            <template v-for="(l, i) in links" :key="i">
                <Link v-if="l.url" :href="l.url" preserve-scroll :class="l.active ? 'bg-brand-600 text-white' : 'text-ink-2 hover:bg-surface-2'" class="min-w-9 rounded-md px-2.5 py-1.5 text-center">{{ l.label }}</Link>
                <span v-else class="min-w-9 rounded-md px-2.5 py-1.5 text-center text-ink-3">{{ l.label }}</span>
            </template>
        </nav>
    </div>
</template>
