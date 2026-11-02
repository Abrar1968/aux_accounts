<script setup>
import Sparkline from '@/Components/charts/Sparkline.vue';
defineProps({ label: String, value: String, hint: String, tone: { type: String, default: 'neutral' }, trend: Array, icon: String });
const tones = { positive: 'text-positive', negative: 'text-negative', warning: 'text-warning', neutral: 'text-ink' };
</script>

<template>
    <div class="card flex min-w-0 flex-col gap-2 p-4">
        <p class="truncate text-xs font-medium uppercase tracking-wide text-ink-3">{{ label }}</p>
        <div class="flex items-end justify-between gap-3">
            <p :class="tones[tone]" class="num truncate text-lg font-semibold tracking-tight sm:text-2xl">{{ value }}</p>
            <Sparkline v-if="trend?.length > 1" :values="trend" class="hidden h-8 w-20 shrink-0 md:block" />
        </div>
        <p v-if="hint" class="truncate text-xs text-ink-2">{{ hint }}</p>
    </div>
</template>
