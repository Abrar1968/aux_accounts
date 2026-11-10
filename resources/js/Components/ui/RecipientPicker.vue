<script setup>
// Email recipients as chips: type to search every known address (project clients, team, past
// recipients), ↑/↓ + Enter to pick, or type any address and press Enter / comma. Backspace removes
// the last chip. "Browse" lists everything grouped by source. v-model is a comma-separated string.
import { computed, nextTick, ref } from 'vue';
import Field from './Field.vue';
import Icon from './Icon.vue';
import { search, highlight } from '@/lib/search';

const props = defineProps({ label: String, error: String, hint: String, contacts: { type: Array, default: () => [] }, placeholder: { type: String, default: 'Type a name, project or email…' } });
const model = defineModel({ type: String, default: '' });
const id = `rp-${Math.random().toString(36).slice(2, 9)}`;
const q = ref('');
const open = ref(false);
const browse = ref(false);
const active = ref(0);
const input = ref(null);
const valid = (e) => /^[^\s@,;]+@[^\s@,;]+\.[^\s@,;]+$/.test(e);

const chips = computed(() => model.value.split(/[,;\s]+/).map((e) => e.trim()).filter(Boolean));
const set = (list) => (model.value = [...new Set(list.map((e) => e.toLowerCase()))].join(', '));
const add = (email) => { if (valid(email)) set([...chips.value, email]); q.value = ''; active.value = 0; nextTick(() => input.value?.focus()); };
const remove = (email) => set(chips.value.filter((e) => e !== email));
const byEmail = computed(() => Object.fromEntries(props.contacts.map((c) => [c.email, c])));

const available = computed(() => props.contacts.filter((c) => !chips.value.includes(c.email)));
const results = computed(() => search(available.value, q.value, (c) => `${c.name} ${c.email} ${c.meta} ${c.source}`, browse.value ? Infinity : 8));
const groups = computed(() => {
    const g = {};
    for (const c of results.value) (g[c.source] ??= []).push(c);
    return Object.entries(g);
});
const flat = computed(() => groups.value.flatMap(([, list]) => list));
const typedNew = computed(() => valid(q.value.trim()) && !byEmail.value[q.value.trim().toLowerCase()] && !chips.value.includes(q.value.trim().toLowerCase()));

function onKey(e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); open.value = true; active.value = Math.min(active.value + 1, flat.value.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = Math.max(active.value - 1, 0); }
    else if (e.key === 'Enter' || e.key === ',' || e.key === ';' || e.key === 'Tab') {
        const typed = q.value.trim().replace(/[,;]$/, '');
        if (!typed && (e.key === 'Tab' || !open.value || !flat.value.length)) return;
        e.preventDefault();
        if (valid(typed) && (typedNew.value || e.key !== 'Enter' || !flat.value.length)) add(typed);
        else if (open.value && flat.value[active.value]) add(flat.value[active.value].email);
    } else if (e.key === 'Backspace' && !q.value && chips.value.length) remove(chips.value.at(-1));
    else if (e.key === 'Escape') { open.value = false; browse.value = false; }
}
function onPaste(e) {
    const text = e.clipboardData?.getData('text') ?? '';
    const found = text.split(/[,;\s]+/).filter(valid);
    if (found.length > 1) { e.preventDefault(); set([...chips.value, ...found]); }
}
const blur = () => setTimeout(() => { const t = q.value.trim(); if (valid(t)) add(t); open.value = false; browse.value = false; }, 150);
const toggleBrowse = () => { browse.value = !browse.value; open.value = browse.value; active.value = 0; input.value?.focus(); };
</script>

<template>
    <Field :label="label" :error="error" :hint="hint" :for="id">
        <div class="relative">
            <div :class="[error && '!border-negative', open && '!border-brand-400 shadow-[0_0_0_4px_color-mix(in_oklab,var(--color-brand-400)_18%,transparent)]']"
                class="field !h-auto flex min-h-[2.625rem] flex-wrap items-center gap-1.5 px-2 py-1.5" @click="input?.focus()">
                <span v-for="e in chips" :key="e" :class="valid(e) ? 'bg-brand-50 text-ink ring-brand-400/30' : 'bg-negative/12 text-negative ring-negative/30'"
                    class="group inline-flex max-w-full items-center gap-1.5 rounded-full py-0.5 pl-2.5 pr-1 text-[12.5px] font-medium ring-1 ring-inset" :title="byEmail[e] ? `${byEmail[e].name} · ${byEmail[e].meta}` : e">
                    <span class="truncate">{{ byEmail[e]?.name && byEmail[e].name.toLowerCase() !== e.split('@')[0] ? `${byEmail[e].name} <${e}>` : e }}</span>
                    <button type="button" class="grid size-5 place-items-center rounded-full text-ink-3 transition hover:bg-negative/15 hover:text-negative" :aria-label="`Remove ${e}`" @click.stop="remove(e)"><Icon name="x" :size="12" /></button>
                </span>
                <input :id="id" ref="input" v-model="q" type="text" role="combobox" :aria-expanded="open" autocomplete="off" :placeholder="chips.length ? '' : placeholder"
                    class="h-7 min-w-[10rem] flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-ink-3"
                    @focus="open = true" @input="open = true; active = 0" @blur="blur" @keydown="onKey" @paste="onPaste" />
                <button type="button" :class="browse ? 'text-brand-600' : 'text-ink-3 hover:text-ink'" class="ml-auto inline-flex h-7 items-center gap-1 rounded-full px-2 text-xs font-semibold transition hover:bg-ink/[0.06]"
                    :title="`Browse all ${contacts.length} saved addresses`" @mousedown.prevent="toggleBrowse"><Icon name="users" :size="14" />{{ contacts.length }}</button>
            </div>
            <div v-if="open && (flat.length || typedNew || q)" role="listbox" class="glass-pop absolute z-40 mt-1.5 max-h-80 w-full overflow-y-auto rounded-2xl p-1.5">
                <button v-if="typedNew" type="button" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm hover:bg-brand-50" @mousedown.prevent="add(q.trim())">
                    <Icon name="plus" :size="15" class="text-brand-600" />Add <b class="truncate">{{ q.trim() }}</b>
                </button>
                <template v-for="[source, list] in groups" :key="source">
                    <p class="eyebrow px-3 pb-1 pt-2.5 text-ink-3">{{ source }} · {{ list.length }}</p>
                    <button v-for="c in list" :key="c.email" type="button" role="option" :aria-selected="flat[active] === c"
                        :class="flat[active] === c ? 'bg-brand-50 shadow-[inset_0_0_0_1px_color-mix(in_oklab,var(--color-brand-400)_25%,transparent)]' : ''"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors" @mouseenter="active = flat.indexOf(c)" @mousedown.prevent="add(c.email)">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-ink/[0.06] text-xs font-bold text-ink-2">{{ (c.name || c.email).charAt(0).toUpperCase() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium" v-html="highlight(c.name, q)" />
                            <span class="block truncate font-mono text-[11px] text-ink-3" v-html="highlight(`${c.email} · ${c.meta}`, q)" />
                        </span>
                    </button>
                </template>
                <p v-if="!flat.length && !typedNew" class="px-3 py-5 text-center text-sm text-ink-3">No saved address matches “{{ q }}”. Type the full email and press Enter.</p>
            </div>
        </div>
    </Field>
</template>
