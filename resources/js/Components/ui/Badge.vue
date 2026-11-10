<script setup>
import { computed } from 'vue';
import { tone as toneFor } from '@/lib/format';
const props = defineProps({ tone: String, label: [String, Number], dot: { type: Boolean, default: true }, size: String });
const t = computed(() => props.tone ?? toneFor(props.label));
const tones = {
    positive: 'bg-positive/12 text-positive ring-positive/25', negative: 'bg-negative/12 text-negative ring-negative/25',
    warning: 'bg-warning/14 text-warning ring-warning/30', info: 'bg-info/12 text-info ring-info/25',
    neutral: 'bg-ink/[0.05] text-ink-2 ring-line-strong', brand: 'bg-brand-50 text-brand-700 ring-brand-400/30',
};
</script>

<template>
    <span :class="[tones[t] ?? tones.neutral, size === 'lg' ? 'px-2.5 py-1 text-xs' : 'px-2 py-0.5 text-[11.5px]']" class="inline-flex max-w-full items-center gap-1.5 truncate rounded-full font-semibold ring-1 ring-inset">
        <span v-if="dot" class="size-1.5 shrink-0 rounded-full bg-current shadow-[0_0_6px_currentColor]" /><slot>{{ label }}</slot>
    </span>
</template>
