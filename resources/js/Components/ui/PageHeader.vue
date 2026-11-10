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
            <Link v-if="back" :href="back" class="no-print group mt-1 grid size-10 shrink-0 place-items-center rounded-full border border-line-strong bg-ink/[0.03] text-ink-2 backdrop-blur transition hover:-translate-x-0.5 hover:border-brand-400/60 hover:bg-brand-50 hover:text-brand-600" aria-label="Back"><Icon name="arrow-left" :size="18" /></Link>
            <span v-else-if="tile" class="no-print mt-1 hidden size-11 shrink-0 place-items-center rounded-2xl border border-brand-400/25 bg-brand-50 text-brand-600 shadow-[0_0_24px_-6px_var(--color-brand-400)] sm:grid"><Icon :name="tile" :size="20" /></span>
            <div class="min-w-0">
                <p v-if="kicker" class="eyebrow flex items-center gap-2 text-brand-600"><span class="size-1.5 rounded-full bg-brand-400 shadow-[0_0_8px_var(--color-brand-400)]" />{{ kicker }}</p>
                <h1 class="mt-1 truncate text-[24px] font-bold tracking-[-0.03em] sm:text-[30px]">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 max-w-2xl text-sm text-ink-3">{{ subtitle }}</p>
            </div>
        </div>
        <div v-if="$slots.default" class="no-print flex flex-wrap items-center gap-2"><slot /></div>
    </div>
</template>
