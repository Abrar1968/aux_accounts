<script setup>
// KPI tile: icon chip, label, big tabular value, hint / delta pill and an area sparkline.
// variant="hero" renders the brand-gradient highlight card.
import { computed } from 'vue';
import Sparkline from '@/Components/charts/Sparkline.vue';
import Icon from './Icon.vue';

const props = defineProps({ label: String, value: String, hint: String, tone: { type: String, default: 'neutral' }, trend: Array, icon: String, delta: [Number, String], variant: String });
const tones = { positive: 'text-positive', negative: 'text-negative', warning: 'text-warning', neutral: 'text-ink' };
const chips = { positive: 'bg-positive/10 text-positive', negative: 'bg-negative/10 text-negative', warning: 'bg-warning/12 text-warning', neutral: 'bg-brand-50 text-brand-600 dark:text-brand-300' };
const stroke = { positive: 'var(--color-positive)', negative: 'var(--color-negative)', warning: 'var(--color-warning)', neutral: 'var(--color-brand-500)' };
const hero = computed(() => props.variant === 'hero');
const deltaNum = computed(() => (props.delta === undefined || props.delta === null || props.delta === '' ? null : Number(props.delta)));
</script>

<template>
    <div :class="hero ? 'bg-brand-gradient text-white shadow-[var(--shadow-brand)] ring-1 ring-inset ring-white/10' : 'card hover:shadow-[var(--shadow-lift)]'"
        class="group relative flex min-w-0 flex-col gap-3 overflow-hidden rounded-[var(--radius-card)] p-4 transition-shadow sm:p-5">
        <div v-if="hero" class="pointer-events-none absolute -right-10 -top-16 size-44 rounded-full bg-white/15 blur-2xl" aria-hidden="true" />
        <div class="relative flex items-center gap-2.5">
            <span v-if="icon" :class="hero ? 'bg-white/18 text-white ring-1 ring-inset ring-white/20' : chips[tone]" class="grid size-8 shrink-0 place-items-center rounded-lg"><Icon :name="icon" :size="16" /></span>
            <p :class="hero ? 'text-white/80' : 'text-ink-2'" class="min-w-0 truncate text-[13px] font-medium">{{ label }}</p>
            <span v-if="deltaNum !== null" :class="hero ? 'bg-white/20 text-white' : deltaNum >= 0 ? 'bg-positive/10 text-positive' : 'bg-negative/10 text-negative'"
                class="num ml-auto shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold">{{ deltaNum >= 0 ? '▲' : '▼' }} {{ Math.abs(deltaNum).toFixed(0) }}%</span>
            <Sparkline v-else-if="trend?.length > 1" :values="trend" :stroke="hero ? '#ffffff' : stroke[tone]" class="ml-auto hidden h-7 w-20 shrink-0 md:block" />
        </div>
        <p :class="hero ? 'text-white' : tones[tone]" class="num relative truncate text-[22px] font-bold leading-none tracking-[-0.02em] sm:text-[26px]">{{ value }}</p>
        <p v-if="hint || $slots.default" :class="hero ? 'text-white/75' : 'text-ink-3'" class="relative truncate text-xs"><slot>{{ hint }}</slot></p>
    </div>
</template>
