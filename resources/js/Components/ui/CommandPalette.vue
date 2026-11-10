<script setup>
// Ctrl/⌘ K: global search (/ajax/search) + quick navigation, keyboard driven.
import { computed, nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { getJson } from '@/Composables/useJson';
import { navigation, modules, can } from '@/lib/nav';
import { search, highlight } from '@/lib/search';
import { shortcuts, keyLabel } from '@/lib/shortcuts';
import { usePermissions } from '@/Composables/usePermissions';

const open = defineModel({ type: Boolean });
const emit = defineEmits(['help']);
const q = ref('');
const results = ref([]);
const active = ref(0);
const input = ref(null);
const { user } = usePermissions();
const modLabel = (k) => modules.find((m) => m.key === k)?.label ?? 'Go to';
const kbd = (href) => { const sc = shortcuts.find((x) => x.href === href); return sc ? sc.keys.map(keyLabel).join('+') : null; };
const actionItems = computed(() => shortcuts.filter((x) => x.href && x.group === 'Create' && can(user.value, x.perm))
    .map((x) => ({ group: 'Actions', title: x.label, subtitle: 'Create', url: x.href, icon: x.icon, kbd: x.keys.map(keyLabel).join('+') })));
const pageItems = computed(() => navigation.flatMap((s) => s.items.map((i) => ({ ...i, section: s.section, module: s.module }))).filter((i) => can(user.value, i.perm))
    .map((i) => ({ group: `Go to · ${modLabel(i.module)}`, title: i.label, subtitle: `${i.section} · ${i.href}`, url: i.href, icon: i.icon, kbd: kbd(i.href) })));
const local = computed(() => {
    const term = q.value.trim();
    const acts = search(actionItems.value, term, (i) => `${i.title} new create add`, term ? 4 : 5);
    const pages = search(pageItems.value, term, (i) => `${i.title} ${i.subtitle}`, term ? 8 : 40);
    return [...acts, ...pages];
});
const all = computed(() => (q.value.trim() ? [...results.value, ...local.value] : local.value).slice(0, 50));
let t;
let seq = 0;
const loading = ref(false);
watch(q, () => {
    clearTimeout(t);
    active.value = 0;
    if (q.value.trim().length < 2) { results.value = []; loading.value = false; return; }
    loading.value = true;
    const mine = ++seq; // ignore answers that arrive after a newer keystroke
    t = setTimeout(async () => {
        try { const r = (await getJson('/ajax/search', { q: q.value })).results; if (mine === seq) results.value = r; } catch { if (mine === seq) results.value = []; } finally { if (mine === seq) loading.value = false; }
    }, 150);
});
watch(open, async (v) => { if (v) { q.value = ''; results.value = []; await nextTick(); input.value?.focus(); } });
function go(item) { open.value = false; router.visit(item.url); }
function onKey(e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); active.value = Math.min(active.value + 1, all.value.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = Math.max(active.value - 1, 0); }
    else if (e.key === 'Enter' && all.value[active.value]) go(all.value[active.value]);
    else if (e.key === 'Escape') open.value = false;
    else if (e.key === '/' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); emit('help'); }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center bg-[#030612]/55 p-0 backdrop-blur-[6px] sm:p-4 sm:pt-[12vh]" @click.self="open = false">
            <div class="glass-pop flex h-full w-full animate-in flex-col overflow-hidden sm:h-auto sm:max-h-[70vh] sm:max-w-xl sm:rounded-[24px]" role="dialog" aria-label="Search">
                <div class="flex items-center gap-3 border-b border-line px-4">
                    <Icon name="search" class="text-brand-600" />
                    <input ref="input" v-model="q" class="h-14 flex-1 bg-transparent text-base outline-none" placeholder="Search projects, clients, emails, phones, payments, leads…" @keydown="onKey" />
                    <span v-if="loading" class="size-4 animate-spin rounded-full border-2 border-brand-400 border-t-transparent" aria-label="Searching" />
                    <button class="rounded-md border border-line px-1.5 py-0.5 font-mono text-[10px] text-ink-3 hover:text-ink" @click="open = false">ESC</button>
                </div>
                <ul class="flex-1 overflow-y-auto p-2" role="listbox">
                    <li v-if="!all.length && !loading" class="px-3 py-10 text-center text-sm text-ink-3">No matches for “{{ q }}”. Try fewer words, a code or part of a phone number.</li>
                    <li v-for="(r, i) in all" :key="r.url + i" role="option" :aria-selected="i === active">
                        <p v-if="i === 0 || all[i - 1].group !== r.group" class="eyebrow px-3 pb-1.5 pt-3 text-ink-3">{{ r.group }}</p>
                        <button :class="i === active ? 'bg-brand-50 shadow-[inset_0_0_0_1px_color-mix(in_oklab,var(--color-brand-400)_25%,transparent)]' : ''" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors" @mouseenter="active = i" @click="go(r)">
                            <span v-if="r.icon" :class="i === active ? 'text-brand-600' : 'text-ink-3'" class="grid size-8 shrink-0 place-items-center rounded-lg bg-ink/[0.05]"><Icon :name="r.icon" :size="16" /></span>
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium" v-html="highlight(r.title, q)" /><span class="block truncate text-xs text-ink-3" v-html="highlight(r.subtitle, q)" /></span>
                            <kbd v-if="r.kbd" class="hidden shrink-0 rounded-md border border-line px-1.5 py-0.5 font-mono text-[10px] text-ink-3 sm:inline">{{ r.kbd }}</kbd>
                            <Icon name="arrow-right" :size="14" :class="i === active ? 'text-brand-600 translate-x-0.5' : 'text-ink-3/60'" class="transition" />
                        </button>
                    </li>
                </ul>
                <div class="hidden items-center gap-4 border-t border-line px-4 py-2.5 font-mono text-[10.5px] text-ink-3 sm:flex">
                    <span><kbd class="rounded border border-line px-1">↑↓</kbd> move</span><span><kbd class="rounded border border-line px-1">Enter</kbd> open</span>
                    <button type="button" class="ml-auto hover:text-ink" @click="emit('help')"><kbd class="rounded border border-line px-1">{{ keyLabel('Ctrl') }}+/</kbd> all shortcuts</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
