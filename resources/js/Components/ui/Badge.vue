<script setup>
import { computed } from 'vue';
import { tone as toneFor } from '@/lib/format';
const props = defineProps({ tone: String, label: [String, Number], dot: { type: Boolean, default: true }, size: String });
const t = computed(() => props.tone ?? toneFor(props.label));
const tones = {
    positive: 'bg-positive/10 text-positive ring-positive/20', negative: 'bg-negative/10 text-negative ring-negative/20',
    warning: 'bg-warning/12 text-warning ring-warning/25', info: 'bg-info/10 text-info ring-info/20',
    neutral: 'bg-surface-2 text-ink-2 ring-line-strong/60', brand: 'bg-brand-50 text-brand-700 ring-brand-200 dark:text-brand-300 dark:ring-brand-500/30',
};
</script>

<template>
    <span :class="[tones[t] ?? tones.neutral, size === 'lg' ? 'px-2.5 py-1 text-xs' : 'px-2 py-0.5 text-[11.5px]']" class="inline-flex max-w-full items-center gap-1.5 truncate rounded-full font-semibold ring-1 ring-inset">
        <span v-if="dot" class="size-1.5 shrink-0 rounded-full bg-current" /><slot>{{ label }}</slot>
    </span>
</template>
