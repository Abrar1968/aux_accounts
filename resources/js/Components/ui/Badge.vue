<script setup>
import { computed } from 'vue';
import { tone as toneFor } from '@/lib/format';
const props = defineProps({ tone: String, label: [String, Number], dot: Boolean });
const t = computed(() => props.tone ?? toneFor(props.label));
const tones = {
    positive: 'bg-positive/12 text-positive', negative: 'bg-negative/12 text-negative', warning: 'bg-warning/14 text-warning',
    info: 'bg-info/12 text-info', neutral: 'bg-surface-2 text-ink-2', brand: 'bg-brand-50 text-brand-700 dark:text-white',
};
</script>

<template>
    <span :class="tones[t]" class="inline-flex max-w-full items-center gap-1.5 truncate rounded-full px-2 py-0.5 text-xs font-medium">
        <span v-if="dot" class="size-1.5 shrink-0 rounded-full bg-current" /><slot>{{ label }}</slot>
    </span>
</template>
