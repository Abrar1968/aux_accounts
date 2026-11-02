<script setup>
// Responsive table: real <table> ≥ 640px, stacked cards below. Columns:
// { key, label, align: 'right', format: fn(value,row), class, primary: true (card title), secondary: true, hideMobile: true }
import { computed, nextTick, onMounted, ref } from 'vue';
import EmptyState from './EmptyState.vue';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    empty: { type: String, default: 'No records match these filters.' },
    clickable: Boolean,
    dense: Boolean,
    footer: Object, // { key: value } totals row
});
const emit = defineEmits(['row']);
const value = (c, r) => (c.format ? c.format(r[c.key], r) : r[c.key] ?? '—');
const primary = computed(() => props.columns.find((c) => c.primary) ?? props.columns[0]);
const secondary = computed(() => props.columns.find((c) => c.secondary));
// Notification deep links (?highlight=CODE or id) flash and scroll to the exact row.
const highlight = ref(typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('highlight') : null);
const isHit = (r) => !!highlight.value && [r.code, r[props.rowKey], r.payroll_code].some((v) => v != null && String(v) === highlight.value);
const root = ref(null);
onMounted(async () => {
    if (!highlight.value) return;
    await nextTick();
    const el = [...(root.value?.querySelectorAll('[data-hit]') ?? [])].find((e) => e.offsetParent !== null);
    el?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    setTimeout(() => (highlight.value = null), 6000);
});
const rest = computed(() => props.columns.filter((c) => c !== primary.value && c !== secondary.value && !c.hideMobile));
</script>

<template>
    <div ref="root" class="min-w-0">
        <EmptyState v-if="!rows.length" :message="empty" />
        <template v-else>
            <!-- desktop / tablet -->
            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-2/70 text-[11px] uppercase tracking-wider text-ink-3">
                        <tr>
                            <th v-for="c in columns" :key="c.key" :class="[c.align === 'right' && 'text-right', c.class]" class="whitespace-nowrap px-4 py-2.5 font-semibold first:pl-5 last:pr-5">{{ c.label }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="(r, i) in rows" :key="r[rowKey] ?? i" :class="[clickable && 'cursor-pointer hover:bg-surface-2/60', isHit(r) && 'row-hit']" :data-hit="isHit(r) || undefined" @click="clickable && emit('row', r)">
                            <td v-for="c in columns" :key="c.key" :class="[c.align === 'right' && 'num text-right', dense ? 'py-2' : 'py-3', c.class]" class="px-4 align-middle first:pl-5 last:pr-5">
                                <slot :name="`cell-${c.key}`" :row="r" :value="r[c.key]">{{ value(c, r) }}</slot>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="footer" class="border-t-2 border-line bg-surface-2/50 font-semibold">
                        <tr><td v-for="c in columns" :key="c.key" :class="c.align === 'right' && 'num text-right'" class="px-4 py-2.5 first:pl-5 last:pr-5">{{ footer[c.key] ?? '' }}</td></tr>
                    </tfoot>
                </table>
            </div>
            <!-- phone -->
            <ul class="divide-y divide-line sm:hidden">
                <li v-for="(r, i) in rows" :key="r[rowKey] ?? i" class="px-4 py-3" :class="[clickable && 'active:bg-surface-2', isHit(r) && 'row-hit']" :data-hit="isHit(r) || undefined" @click="clickable && emit('row', r)">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 font-medium"><slot :name="`cell-${primary.key}`" :row="r" :value="r[primary.key]">{{ value(primary, r) }}</slot></div>
                        <div v-if="secondary" class="shrink-0 text-right"><slot :name="`cell-${secondary.key}`" :row="r" :value="r[secondary.key]">{{ value(secondary, r) }}</slot></div>
                    </div>
                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                        <template v-for="c in rest" :key="c.key">
                            <dt class="truncate text-ink-3">{{ c.label }}</dt>
                            <dd class="min-w-0 truncate text-right text-ink" :class="c.align === 'right' && 'num'"><slot :name="`cell-${c.key}`" :row="r" :value="r[c.key]">{{ value(c, r) }}</slot></dd>
                        </template>
                    </dl>
                </li>
            </ul>
        </template>
    </div>
</template>
