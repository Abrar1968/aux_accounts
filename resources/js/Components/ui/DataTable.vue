<script setup>
// Responsive table: real <table> ≥ 640px, stacked cards below. Columns:
// { key, label, align: 'right', format: fn(value,row), class, primary: true (card title), secondary: true,
//   hideMobile: true, avatar: true (initials bubble on the primary cell), sub: fn(row) (muted 2nd line) }
// Slots: cell-<key>, actions (row edit/delete — renders a trailing column).
import { computed, nextTick, onMounted, ref, useSlots } from 'vue';
import EmptyState from './EmptyState.vue';
import Avatar from './Avatar.vue';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    empty: { type: String, default: 'No records match these filters.' },
    emptyTitle: { type: String, default: 'Nothing to show' },
    clickable: Boolean,
    dense: Boolean,
    footer: Object, // { key: value } totals row
});
const emit = defineEmits(['row']);
const slots = useSlots();
const value = (c, r) => (c.format ? c.format(r[c.key], r) : r[c.key] ?? '—');
const primary = computed(() => props.columns.find((c) => c.primary) ?? props.columns[0]);
const secondary = computed(() => props.columns.find((c) => c.secondary));
const rest = computed(() => props.columns.filter((c) => c !== primary.value && c !== secondary.value && !c.hideMobile));
const hasActions = computed(() => !!slots.actions);
const avatarName = (c, r) => { const v = value(c, r); return typeof v === 'string' ? v : String(r[c.key] ?? ''); };

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
</script>

<template>
    <div ref="root" class="min-w-0">
        <EmptyState v-if="!rows.length" :title="emptyTitle" :message="empty" />
        <template v-else>
            <!-- desktop / tablet -->
            <div class="relative hidden overflow-x-auto sm:block">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-line bg-ink/[0.025]">
                            <th v-for="c in columns" :key="c.key" :class="[c.align === 'right' && 'text-right', c.class]" class="eyebrow whitespace-nowrap px-4 py-3 !text-[10px] text-ink-3 first:pl-5 last:pr-5">{{ c.label }}</th>
                            <th v-if="hasActions" class="w-px px-4 py-3 pr-5"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line/80">
                        <tr v-for="(r, i) in rows" :key="r[rowKey] ?? i" :class="[clickable && 'cursor-pointer', isHit(r) && 'row-hit']" :data-hit="isHit(r) || undefined"
                            class="group transition-colors duration-150 hover:bg-brand-50 hover:shadow-[inset_3px_0_0_var(--color-brand-400)]" @click="clickable && emit('row', r)">
                            <td v-for="c in columns" :key="c.key" :class="[c.align === 'right' && 'num text-right font-mono text-[12.5px] font-semibold text-ink', dense ? 'py-2.5' : 'py-3.5', c.class, c === primary && 'font-medium text-ink']" class="px-4 align-middle text-ink-2 first:pl-5 last:pr-5">
                                <div v-if="c === primary && (c.avatar || c.sub)" class="flex min-w-0 items-center gap-3">
                                    <Avatar v-if="c.avatar" :name="avatarName(c, r)" size="sm" />
                                    <div class="min-w-0">
                                        <slot :name="`cell-${c.key}`" :row="r" :value="r[c.key]"><span class="block truncate font-semibold text-ink transition-colors group-hover:text-brand-700">{{ value(c, r) }}</span></slot>
                                        <span v-if="c.sub" class="block truncate text-xs font-normal text-ink-3">{{ c.sub(r) }}</span>
                                    </div>
                                </div>
                                <slot v-else :name="`cell-${c.key}`" :row="r" :value="r[c.key]">{{ value(c, r) }}</slot>
                            </td>
                            <td v-if="hasActions" class="w-px whitespace-nowrap px-4 pr-5 text-right"><slot name="actions" :row="r" /></td>
                        </tr>
                    </tbody>
                    <tfoot v-if="footer" class="border-t border-line-strong bg-ink/[0.03] font-bold text-ink">
                        <tr><td v-for="c in columns" :key="c.key" :class="c.align === 'right' && 'num text-right font-mono text-[12.5px]'" class="px-4 py-3 first:pl-5 last:pr-5">{{ footer[c.key] ?? '' }}</td><td v-if="hasActions" /></tr>
                    </tfoot>
                </table>
            </div>
            <!-- phone -->
            <ul class="divide-y divide-line sm:hidden">
                <li v-for="(r, i) in rows" :key="r[rowKey] ?? i" class="px-4 py-3.5 transition-colors" :class="[clickable && 'active:bg-brand-50', isHit(r) && 'row-hit']" :data-hit="isHit(r) || undefined" @click="clickable && emit('row', r)">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <Avatar v-if="primary.avatar" :name="avatarName(primary, r)" size="sm" />
                            <div class="min-w-0 font-semibold">
                                <slot :name="`cell-${primary.key}`" :row="r" :value="r[primary.key]">{{ value(primary, r) }}</slot>
                                <span v-if="primary.sub" class="block truncate text-xs font-normal text-ink-3">{{ primary.sub(r) }}</span>
                            </div>
                        </div>
                        <div v-if="secondary" class="shrink-0 text-right"><slot :name="`cell-${secondary.key}`" :row="r" :value="r[secondary.key]">{{ value(secondary, r) }}</slot></div>
                    </div>
                    <dl class="mt-2.5 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                        <template v-for="c in rest" :key="c.key">
                            <dt class="truncate text-ink-3">{{ c.label }}</dt>
                            <dd class="min-w-0 truncate text-right text-ink" :class="c.align === 'right' && 'num font-mono text-[11.5px] font-semibold'"><slot :name="`cell-${c.key}`" :row="r" :value="r[c.key]">{{ value(c, r) }}</slot></dd>
                        </template>
                    </dl>
                    <div v-if="hasActions" class="mt-2 flex justify-end border-t border-dashed border-line pt-2"><slot name="actions" :row="r" /></div>
                </li>
            </ul>
        </template>
    </div>
</template>
