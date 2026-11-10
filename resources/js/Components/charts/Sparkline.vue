<script setup>
import { computed } from 'vue';
const props = defineProps({ values: { type: Array, default: () => [] }, stroke: { type: String, default: 'var(--color-brand-400)' } });
const id = `sp-${Math.random().toString(36).slice(2, 8)}`;
const pts = computed(() => {
    const v = props.values.map(Number);
    if (v.length < 2) return [];
    const min = Math.min(...v), max = Math.max(...v), span = max - min || 1;
    return v.map((y, i) => [(i / (v.length - 1)) * 100, 27 - ((y - min) / span) * 23]);
});
// Smooth (Catmull-Rom → Bézier) line
const line = computed(() => pts.value.reduce((d, p, i, a) => {
    if (!i) return `M${p[0]},${p[1]}`;
    const p0 = a[i - 2] ?? a[i - 1], p1 = a[i - 1], p3 = a[i + 1] ?? p;
    const c1 = [p1[0] + (p[0] - p0[0]) / 6, p1[1] + (p[1] - p0[1]) / 6], c2 = [p[0] - (p3[0] - p1[0]) / 6, p[1] - (p3[1] - p1[1]) / 6];
    return `${d} C${c1[0]},${c1[1]} ${c2[0]},${c2[1]} ${p[0]},${p[1]}`;
}, ''));
const area = computed(() => (line.value ? `${line.value} L100,30 L0,30 Z` : ''));
</script>

<template>
    <svg viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true" class="overflow-visible">
        <defs><linearGradient :id="id" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" :stop-color="stroke" stop-opacity=".28" /><stop offset="100%" :stop-color="stroke" stop-opacity="0" /></linearGradient></defs>
        <path :d="area" :fill="`url(#${id})`" />
        <path :d="line" fill="none" :stroke="stroke" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
    </svg>
</template>
