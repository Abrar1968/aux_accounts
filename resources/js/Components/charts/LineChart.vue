<script setup>
// Multi-series line/area chart with hover guide.
import { computed, ref } from 'vue';
import { niceTicks, compact, SERIES } from './chart';
import { useChartWidth } from './useChartWidth';
import { money } from '@/lib/format';

const props = defineProps({ labels: Array, series: Array, height: { type: Number, default: 220 }, area: Boolean, title: String });
const { el, width: W } = useChartWidth();
const P = { l: 44, r: 10, t: 10, b: 26 };
const hover = ref(null);
const all = computed(() => props.series.flatMap((s) => s.values.map(Number)));
const ticks = computed(() => niceTicks(Math.min(0, ...all.value), Math.max(0, ...all.value)));
const x = (i) => P.l + (props.labels.length < 2 ? 0 : (i / (props.labels.length - 1)) * (W.value - P.l - P.r));
const y = (v) => { const [lo, hi] = [ticks.value[0], ticks.value.at(-1)]; return P.t + (1 - (v - lo) / (hi - lo || 1)) * (props.height - P.t - P.b); };
const line = (vals) => vals.map((v, i) => `${i ? 'L' : 'M'}${x(i)},${y(v)}`).join(' ');
const fill = (vals) => `${line(vals)} L${x(vals.length - 1)},${y(0)} L${x(0)},${y(0)} Z`;
function move(e) {
    const r = e.currentTarget.getBoundingClientRect();
    const px = ((e.clientX - r.left) / r.width) * W.value;
    hover.value = Math.max(0, Math.min(props.labels.length - 1, Math.round(((px - P.l) / (W.value - P.l - P.r)) * (props.labels.length - 1))));
}
</script>

<template>
    <figure ref="el" class="relative min-w-0">
        <svg :viewBox="`0 0 ${W} ${height}`" class="h-auto w-full touch-pan-y" role="img" :aria-label="title" @mousemove="move" @mouseleave="hover = null">
            <g class="text-[10px]" fill="var(--color-ink-3)">
                <g v-for="t in ticks" :key="t"><line :x1="P.l" :x2="W - P.r" :y1="y(t)" :y2="y(t)" stroke="var(--color-line)" :stroke-dasharray="t === 0 ? '' : '3 3'" /><text :x="P.l - 6" :y="y(t) + 3" text-anchor="end">{{ compact(t) }}</text></g>
                <text v-for="(l, i) in labels" :key="l" :x="x(i)" :y="height - 8" text-anchor="middle">{{ l }}</text>
            </g>
            <g v-for="(s, si) in series" :key="s.name">
                <path v-if="area" :d="fill(s.values)" :fill="s.color ?? SERIES[si]" opacity="0.08" />
                <path :d="line(s.values)" fill="none" :stroke="s.color ?? SERIES[si]" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round" />
                <circle v-for="(v, i) in s.values" :key="i" :cx="x(i)" :cy="y(v)" :r="hover === i ? 4 : 2.5" :fill="s.color ?? SERIES[si]" />
            </g>
            <line v-if="hover !== null" :x1="x(hover)" :x2="x(hover)" :y1="P.t" :y2="height - P.b" stroke="var(--color-ink-3)" stroke-dasharray="2 3" />
        </svg>
        <div v-if="hover !== null" class="card pointer-events-none absolute top-2 z-10 px-3 py-2 text-xs shadow-[var(--shadow-pop)]" :style="{ left: `min(calc(${(x(hover) / W) * 100}% + 8px), calc(100% - 11rem))` }">
            <p class="font-semibold">{{ labels[hover] }}</p>
            <p v-for="(s, si) in series" :key="s.name" class="num flex justify-between gap-4"><span class="text-ink-2">{{ s.name }}</span><span>{{ money(s.values[hover]) }}</span></p>
        </div>
        <figcaption class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-2">
            <span v-for="(s, si) in series" :key="s.name" class="inline-flex items-center gap-1.5"><span class="h-0.5 w-3 rounded" :style="{ background: s.color ?? SERIES[si] }" />{{ s.name }}</span>
        </figcaption>
    </figure>
</template>
