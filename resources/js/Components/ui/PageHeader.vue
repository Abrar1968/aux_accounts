<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ title: String, subtitle: String, back: String });
</script>

<template>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <Link v-if="back" :href="back" class="no-print mb-1 inline-flex items-center gap-1 text-xs font-medium text-ink-3 hover:text-ink">← Back</Link>
            <h1 class="truncate text-xl font-semibold tracking-tight sm:text-2xl">{{ title }}</h1>
            <p v-if="subtitle" class="mt-0.5 text-sm text-ink-2">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.default" class="no-print flex flex-wrap items-center gap-2"><slot /></div>
    </div>
</template>
