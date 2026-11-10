<script setup>
// Grouped bar chart. series: [{ name, values: [] , color }], labels: [].
import { computed, ref } from 'vue';
import { niceTicks, compact, SERIES } from './chart';
import { useChartWidth } from './useChartWidth';
import { money } from '@/lib/format';

const props = defineProps({ labels: Array, series: Array, height: { type: Number, default: 220 }, title: String });
const { el, width: W } = useChartWidth();
const P = { l: 44, r: 8, t: 10, b: 26 };
const hover = ref(null);
const ticks = computed(() => niceTicks(Math.min(0, ...props.series.flatMap((s) => s.values)), Math.max(0, ...props.series.flatMap((s) => s.values))));
const y = (v) => { const [lo, hi] = [ticks.value[0], ticks.value.at(-1)]; return P.t + (1 - (v - lo) / (hi - lo || 1)) * (props.height - P.t - P.b); };
const band = computed(() => (W.value - P.l - P.r) / Math.max(1, props.labels.length));
const bw = computed(() => Math.min(18, (band.value * 0.62) / props.series.length));
const gid = `bc-${Math.random().toString(36).slice(2, 8)}`;
const color = (s, si) => s.color ?? SERIES[si];
</script>

<template>
    <figure ref="el" class="min-w-0">
        <svg :viewBox="`0 0 ${W} ${height}`" class="h-auto w-full" role="img" :aria-label="title">
            <defs>
                <linearGradient v-for="(s, si) in series" :id="`${gid}-${si}`" :key="si" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" :stop-color="color(s, si)" /><stop offset="100%" :stop-color="color(s, si)" stop-opacity=".55" /></linearGradient>
            </defs>
            <g class="font-mono text-[9.5px]" fill="var(--color-ink-3)">
                <g v-for="t in ticks" :key="t"><line :x1="P.l" :x2="W - P.r" :y1="y(t)" :y2="y(t)" stroke="var(--color-line)" :stroke-dasharray="t === 0 ? '' : '3 3'" /><text :x="P.l - 6" :y="y(t) + 3" text-anchor="end">{{ compact(t) }}</text></g>
                <text v-for="(l, i) in labels" :key="l" :x="P.l + band * i + band / 2" :y="height - 8" text-anchor="middle">{{ l }}</text>
            </g>
            <g v-for="(s, si) in series" :key="s.name">
                <rect v-for="(v, i) in s.values" :key="i" :x="P.l + band * i + band / 2 - (bw * series.length) / 2 + bw * si + 1" :width="bw - 2"
                    :y="Math.min(y(v), y(0))" :height="Math.max(1, Math.abs(y(v) - y(0)))" rx="5" :fill="`url(#${gid}-${si})`"
                    :opacity="hover === null || hover === i ? 1 : 0.45" @mouseenter="hover = i" @mouseleave="hover = null"><title>{{ s.name }} · {{ labels[i] }}: {{ money(v) }}</title></rect>
            </g>
        </svg>
        <figcaption class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs font-medium text-ink-2">
            <span v-for="(s, si) in series" :key="s.name" class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full" :style="{ background: s.color ?? SERIES[si] }" />{{ s.name }}</span>
        </figcaption>
        <table class="sr-only"><tr><th>Month</th><th v-for="s in series" :key="s.name">{{ s.name }}</th></tr><tr v-for="(l, i) in labels" :key="l"><td>{{ l }}</td><td v-for="s in series" :key="s.name">{{ s.values[i] }}</td></tr></table>
    </figure>
</template>
