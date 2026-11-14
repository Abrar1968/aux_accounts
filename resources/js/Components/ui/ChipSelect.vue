<script setup>
// Multi-select as chips: click names from the list (most used first) or type a new one and press
// Enter. v-model stays a comma-separated string ("Rizwan, Abrar") so the API is unchanged.
import { computed, nextTick, ref } from 'vue';
import Field from './Field.vue';
import Icon from './Icon.vue';
import { search, highlight } from '@/lib/search';

const props = defineProps({
    label: String, error: String, hint: String, options: { type: Array, default: () => [] }, // [{ name, meta }]
    placeholder: { type: String, default: 'Select or type a name…' }, createLabel: { type: String, default: 'Add' },
});
const model = defineModel({ type: String, default: '' });
const id = `cs-${Math.random().toString(36).slice(2, 9)}`;
const q = ref('');
const open = ref(false);
const active = ref(0);
const input = ref(null);

const chips = computed(() => [...new Set(String(model.value ?? '').split(/[,;&+/]+/).map((n) => n.replace(/\s+/g, ' ').trim()).filter(Boolean))]);
const has = (name) => chips.value.some((c) => c.toLowerCase() === name.toLowerCase());
const set = (list) => (model.value = list.join(', '));
const add = (name) => { const n = name.replace(/\s+/g, ' ').trim(); if (n && !has(n)) set([...chips.value, n]); q.value = ''; active.value = 0; nextTick(() => input.value?.focus()); };
const remove = (name) => set(chips.value.filter((c) => c.toLowerCase() !== name.toLowerCase()));
const toggle = (name) => (has(name) ? remove(name) : add(name));

const results = computed(() => search(props.options, q.value, (o) => `${o.name} ${o.meta ?? ''}`));
const typedNew = computed(() => { const t = q.value.trim(); return t.length > 1 && !props.options.some((o) => o.name.toLowerCase() === t.toLowerCase()) && !has(t); });

function onKey(e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); open.value = true; active.value = Math.min(active.value + 1, results.value.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = Math.max(active.value - 1, 0); }
    else if (e.key === 'Enter' || e.key === ',') {
        if (!q.value.trim() && e.key === 'Enter' && !open.value) return;
        e.preventDefault();
        if (typedNew.value && (e.key === ',' || !results.value.length)) add(q.value);
        else if (results.value[active.value]) toggle(results.value[active.value].name);
        else if (q.value.trim()) add(q.value);
    } else if (e.key === 'Backspace' && !q.value && chips.value.length) remove(chips.value.at(-1));
    else if (e.key === 'Escape') open.value = false;
}
// a typed name counts even if the user goes straight to Save without pressing Enter
function blur() {
    const t = q.value.replace(/\s+/g, ' ').trim();
    if (t && !has(t)) set([...chips.value, t]);
    q.value = '';
    open.value = false;
}
</script>

<template>
    <Field :label="label" :error="error" :hint="hint" :for="id">
        <div class="relative">
            <div :class="[error && '!border-negative', open && '!border-brand-400 shadow-[0_0_0_4px_color-mix(in_oklab,var(--color-brand-400)_18%,transparent)]']"
                class="field !h-auto flex min-h-[2.625rem] cursor-text flex-wrap items-center gap-1.5 px-2 py-1.5" @click="input?.focus(); open = true">
                <span v-for="c in chips" :key="c" class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-brand-50 py-0.5 pl-2.5 pr-1 text-[12.5px] font-semibold text-ink ring-1 ring-inset ring-brand-400/30">
                    <span class="truncate">{{ c }}</span>
                    <button type="button" class="grid size-5 place-items-center rounded-full text-ink-3 transition hover:bg-negative/15 hover:text-negative" :aria-label="`Remove ${c}`" @click.stop="remove(c)"><Icon name="x" :size="12" /></button>
                </span>
                <input :id="id" ref="input" v-model="q" type="text" role="combobox" :aria-expanded="open" autocomplete="off" :placeholder="chips.length ? '' : placeholder"
                    class="h-7 min-w-[8rem] flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-ink-3" @focus="open = true" @input="open = true; active = 0" @blur="blur" @keydown="onKey" />
                <Icon name="chevron-down" :size="16" :class="open && 'rotate-180'" class="ml-auto shrink-0 text-ink-3 transition-transform" />
            </div>
            <div v-if="open" role="listbox" aria-multiselectable="true" class="glass-pop absolute z-40 mt-1.5 max-h-72 w-full overflow-y-auto rounded-2xl p-1.5">
                <button v-if="typedNew" type="button" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm hover:bg-brand-50" @mousedown.prevent="add(q)">
                    <Icon name="plus" :size="15" class="text-brand-600" />{{ createLabel }} <b class="truncate">“{{ q.trim() }}”</b>
                </button>
                <button v-for="(o, i) in results" :key="o.name" type="button" role="option" :aria-selected="has(o.name)"
                    :class="i === active ? 'bg-brand-50' : ''" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors" @mouseenter="active = i" @mousedown.prevent="toggle(o.name)">
                    <span :class="has(o.name) ? 'border-brand-400 bg-brand-400 text-on-brand' : 'border-line-strong'" class="grid size-[18px] shrink-0 place-items-center rounded-[6px] border transition"><Icon v-if="has(o.name)" name="check" :size="12" /></span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium" v-html="highlight(o.name, q)" /><span v-if="o.meta" class="block truncate text-[11px] text-ink-3">{{ o.meta }}</span></span>
                </button>
                <p v-if="!results.length && !typedNew" class="px-3 py-4 text-center text-sm text-ink-3">Type a name and press Enter to add a new one.</p>
            </div>
        </div>
    </Field>
</template>
