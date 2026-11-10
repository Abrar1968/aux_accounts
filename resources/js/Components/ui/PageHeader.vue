<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { findNav } from '@/lib/nav';

const props = defineProps({ title: String, subtitle: String, back: String, icon: String, eyebrow: String });
const nav = computed(() => findNav(usePage().url));
const tile = computed(() => props.icon ?? nav.value?.icon);
const kicker = computed(() => props.eyebrow ?? nav.value?.section);
</script>

<template>
    <div class="mb-6 flex animate-in flex-col gap-4 sm:mb-7 md:flex-row md:items-end md:justify-between">
        <div class="flex min-w-0 items-start gap-3.5">
            <Link v-if="back" :href="back" class="no-print mt-1 grid size-10 shrink-0 place-items-center rounded-xl border border-line bg-surface text-ink-2 shadow-[var(--shadow-xs)] transition hover:border-line-strong hover:text-ink" aria-label="Back"><Icon name="arrow-left" :size="18" /></Link>
            <span v-else-if="tile" class="no-print mt-0.5 hidden size-11 shrink-0 place-items-center rounded-2xl bg-brand-gradient text-white shadow-[var(--shadow-brand)] ring-1 ring-inset ring-white/15 sm:grid"><Icon :name="tile" :size="20" /></span>
            <div class="min-w-0">
                <p v-if="kicker" class="text-[11px] font-semibold uppercase tracking-[0.12em] text-brand-600 dark:text-brand-300">{{ kicker }}</p>
                <h1 class="truncate text-[22px] font-bold tracking-[-0.02em] sm:text-[26px]">{{ title }}</h1>
                <p v-if="subtitle" class="mt-0.5 text-sm text-ink-2">{{ subtitle }}</p>
            </div>
        </div>
        <div v-if="$slots.default" class="no-print flex flex-wrap items-center gap-2"><slot /></div>
    </div>
</template>
