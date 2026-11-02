<script setup>
import { computed } from 'vue';
import { SERIES } from './chart';
import { money } from '@/lib/format';

const props = defineProps({ items: { type: Array, default: () => [] }, center: String, centerLabel: String }); // [{label, amount}]
const total = computed(() => props.items.reduce((s, i) => s + Number(i.amount), 0) || 1);
const arcs = computed(() => {
    let a = -Math.PI / 2;
    return props.items.map((it, i) => {
        const f = Number(it.amount) / total.value, a0 = a, a1 = a + f * Math.PI * 2;
        a = a1;
        const r = 40, large = a1 - a0 > Math.PI ? 1 : 0;
        const p = (t) => `${50 + r * Math.cos(t)},${50 + r * Math.sin(t)}`;
        return { ...it, f, color: SERIES[i % SERIES.length], d: f >= 0.9999 ? `M50,10 A40,40 0 1 1 49.99,10` : `M${p(a0)} A${r},${r} 0 ${large} 1 ${p(a1)}` };
    });
});
</script>

<template>
    <div class="flex flex-col items-center gap-4 sm:flex-row">
        <svg viewBox="0 0 100 100" class="size-40 shrink-0 drop-shadow-sm" role="img" aria-label="Breakdown">
            <circle cx="50" cy="50" r="40" fill="none" stroke="var(--color-surface-2)" stroke-width="11" />
            <path v-for="a in arcs" :key="a.label" :d="a.d" fill="none" :stroke="a.color" stroke-width="11" stroke-linecap="butt"><title>{{ a.label }}: {{ money(a.amount) }}</title></path>
            <text x="50" y="49" text-anchor="middle" class="num" font-size="10" font-weight="600" fill="var(--color-ink)">{{ center }}</text>
            <text x="50" y="60" text-anchor="middle" font-size="6" fill="var(--color-ink-3)">{{ centerLabel }}</text>
        </svg>
        <ul class="w-full min-w-0 space-y-1.5 text-sm">
            <li v-for="a in arcs" :key="a.label" class="flex items-center gap-2"><span class="size-2.5 shrink-0 rounded-full" :style="{ background: a.color }" /><span class="min-w-0 flex-1 truncate text-ink-2">{{ a.label }}</span><span class="num font-medium">{{ money(a.amount, { compact: true }) }}</span><span class="num w-10 text-right text-xs text-ink-3">{{ Math.round(a.f * 100) }}%</span></li>
        </ul>
    </div>
</template>
