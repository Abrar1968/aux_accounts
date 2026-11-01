<script setup>
// Phase-1 placeholder: shows a page's props so every route is usable before the Phase-3 UI.
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({ title: { type: String, required: true }, description: String });
const page = usePage();
const ignore = ['app', 'auth', 'flash', 'notifications', 'pusher', 'errors'];
const sections = computed(() =>
    Object.entries(page.props)
        .filter(([k]) => !ignore.includes(k))
        .map(([key, value]) => {
            const rows = Array.isArray(value) ? value : Array.isArray(value?.data) ? value.data : null;
            return { key, rows, value, total: value?.meta?.total ?? value?.total ?? rows?.length };
        }),
);
const columns = (rows) => Object.keys(rows[0] ?? {}).filter((c) => typeof rows[0][c] !== 'object').slice(0, 8);
</script>

<template>
    <div class="space-y-6">
        <div><h1 class="text-xl font-semibold tracking-tight sm:text-2xl">{{ title }}</h1><p v-if="description" class="mt-1 text-sm text-ink-2">{{ description }}</p></div>
        <section v-for="s in sections" :key="s.key" class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold capitalize">{{ s.key.replaceAll('_', ' ') }}</h2>
                <span v-if="s.total !== undefined" class="text-xs text-ink-3">{{ s.total }} rows</span>
            </header>
            <div v-if="s.rows && s.rows.length" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-2 text-xs uppercase tracking-wide text-ink-3"><tr><th v-for="c in columns(s.rows)" :key="c" class="px-4 py-2 font-medium">{{ c.replaceAll('_', ' ') }}</th></tr></thead>
                    <tbody><tr v-for="(r, i) in s.rows.slice(0, 50)" :key="i" class="border-t border-line"><td v-for="c in columns(s.rows)" :key="c" class="num whitespace-nowrap px-4 py-2">{{ r[c] ?? '—' }}</td></tr></tbody>
                </table>
            </div>
            <pre v-else class="max-h-80 overflow-auto p-4 text-xs text-ink-2">{{ JSON.stringify(s.value, null, 2) }}</pre>
        </section>
    </div>
</template>
