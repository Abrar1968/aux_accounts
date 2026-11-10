<script setup>
// KPI tile: icon chip, label, big tabular value, hint / delta pill and an area sparkline.
// variant="hero" renders the brand-gradient highlight card.
import { computed } from 'vue';
import Sparkline from '@/Components/charts/Sparkline.vue';
import Icon from './Icon.vue';

const props = defineProps({ label: String, value: String, hint: String, tone: { type: String, default: 'neutral' }, trend: Array, icon: String, delta: [Number, String], variant: String });
const tones = { positive: 'text-positive', negative: 'text-negative', warning: 'text-warning', neutral: 'text-ink' };
const chips = { positive: 'bg-positive/12 text-positive ring-positive/25', negative: 'bg-negative/12 text-negative ring-negative/25', warning: 'bg-warning/14 text-warning ring-warning/25', neutral: 'bg-brand-50 text-brand-600 ring-brand-400/25' };
const stroke = { positive: 'var(--color-positive)', negative: 'var(--color-negative)', warning: 'var(--color-warning)', neutral: 'var(--color-brand-400)' };
const hero = computed(() => props.variant === 'hero');
const deltaNum = computed(() => (props.delta === undefined || props.delta === null || props.delta === '' ? null : Number(props.delta)));
</script>

<template>
    <div :class="hero ? 'bg-brand-gradient text-on-brand shadow-[var(--shadow-brand)] ring-1 ring-inset ring-white/30' : 'card spotlight'"
        class="lift group relative flex min-w-0 flex-col gap-3 overflow-hidden rounded-[var(--radius-card)] p-4 sm:p-5">
        <div v-if="hero" class="pointer-events-none absolute -right-10 -top-16 size-44 rounded-full bg-white/30 blur-2xl transition-transform duration-700 group-hover:scale-125" aria-hidden="true" />
        <div v-if="hero" class="pointer-events-none absolute inset-0 opacity-[0.12] [background-image:linear-gradient(rgb(10_17_40/.5)_1px,transparent_1px),linear-gradient(90deg,rgb(10_17_40/.5)_1px,transparent_1px)] [background-size:22px_22px] [mask-image:linear-gradient(120deg,transparent_40%,black)]" aria-hidden="true" />
        <div class="relative flex items-center gap-2.5">
            <span v-if="icon" :class="hero ? 'bg-navy/10 text-on-brand ring-navy/15' : chips[tone]" class="grid size-8 shrink-0 place-items-center rounded-xl ring-1 ring-inset transition-transform duration-300 group-hover:scale-110"><Icon :name="icon" :size="16" /></span>
            <p :class="hero ? 'text-on-brand/75' : 'text-ink-3'" class="eyebrow min-w-0 truncate max-sm:!tracking-[0.08em]">{{ label }}</p>
            <span v-if="deltaNum !== null" :class="hero ? 'bg-navy/12 text-on-brand' : deltaNum >= 0 ? 'bg-positive/12 text-positive' : 'bg-negative/12 text-negative'"
                class="ml-auto shrink-0 rounded-full px-2 py-0.5 font-mono text-[10.5px] font-semibold">{{ deltaNum >= 0 ? '▲' : '▼' }} {{ Math.abs(deltaNum).toFixed(0) }}%</span>
        </div>
        <div class="relative flex min-w-0 items-end gap-3">
            <p :class="hero ? 'text-on-brand' : tones[tone]" class="min-w-0 flex-1 truncate text-[22px] font-bold leading-none tracking-[-0.03em] sm:text-[27px]">{{ value }}</p>
            <Sparkline v-if="deltaNum === null && trend?.length > 1" :values="trend" :stroke="hero ? '#0a1128' : stroke[tone]" class="hidden h-8 w-[4.5rem] shrink-0 opacity-90 transition-opacity group-hover:opacity-100 md:block" />
        </div>
        <p v-if="hint || $slots.default" :class="hero ? 'text-on-brand/70' : 'text-ink-3'" class="relative truncate text-xs"><slot>{{ hint }}</slot></p>
    </div>
</template>
