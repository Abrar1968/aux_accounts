<script setup>
import { computed } from 'vue';
const props = defineProps({ values: { type: Array, default: () => [] }, stroke: { type: String, default: 'var(--color-brand-500)' } });
const path = computed(() => {
    const v = props.values.map(Number);
    if (v.length < 2) return '';
    const min = Math.min(...v), max = Math.max(...v), span = max - min || 1;
    return v.map((y, i) => `${i ? 'L' : 'M'}${(i / (v.length - 1)) * 100},${28 - ((y - min) / span) * 24}`).join(' ');
});
</script>

<template>
    <svg viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true"><path :d="path" fill="none" :stroke="stroke" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" /></svg>
</template>
