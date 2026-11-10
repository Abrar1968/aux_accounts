<script setup>
// Ctrl/⌘ K: global search (/ajax/search) + quick navigation, keyboard driven.
import { computed, nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { getJson } from '@/Composables/useJson';
import { navigation, modules, can } from '@/lib/nav';
import { usePermissions } from '@/Composables/usePermissions';

const open = defineModel({ type: Boolean });
const q = ref('');
const results = ref([]);
const active = ref(0);
const input = ref(null);
const { user } = usePermissions();
const modLabel = (k) => modules.find((m) => m.key === k)?.label ?? 'Go to';
const pages = computed(() => navigation.flatMap((s) => s.items.map((i) => ({ ...i, section: s.section, module: s.module }))).filter((i) => can(user.value, i.perm))
    .filter((i) => !q.value || `${i.label} ${i.section}`.toLowerCase().includes(q.value.toLowerCase()))
    .map((i) => ({ group: `Go to · ${modLabel(i.module)}`, title: i.label, subtitle: `${i.section} · ${i.href}`, url: i.href, icon: i.icon })));
const all = computed(() => [...results.value, ...pages.value].slice(0, 40));
let t;
watch(q, () => {
    clearTimeout(t);
    active.value = 0;
    if (q.value.trim().length < 2) { results.value = []; return; }
    t = setTimeout(async () => { try { results.value = (await getJson('/ajax/search', { q: q.value })).results; } catch { results.value = []; } }, 180);
});
watch(open, async (v) => { if (v) { q.value = ''; results.value = []; await nextTick(); input.value?.focus(); } });
function go(item) { open.value = false; router.visit(item.url); }
function onKey(e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); active.value = Math.min(active.value + 1, all.value.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = Math.max(active.value - 1, 0); }
    else if (e.key === 'Enter' && all.value[active.value]) go(all.value[active.value]);
    else if (e.key === 'Escape') open.value = false;
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center bg-[#030612]/55 p-0 backdrop-blur-[6px] sm:p-4 sm:pt-[12vh]" @click.self="open = false">
            <div class="glass-pop flex h-full w-full animate-in flex-col overflow-hidden sm:h-auto sm:max-h-[70vh] sm:max-w-xl sm:rounded-[24px]" role="dialog" aria-label="Search">
                <div class="flex items-center gap-3 border-b border-line px-4">
                    <Icon name="search" class="text-brand-600" />
                    <input ref="input" v-model="q" class="h-14 flex-1 bg-transparent text-base outline-none" placeholder="Search projects, payments, expenses, leads…" @keydown="onKey" />
                    <button class="rounded-md border border-line px-1.5 py-0.5 font-mono text-[10px] text-ink-3 hover:text-ink" @click="open = false">ESC</button>
                </div>
                <ul class="flex-1 overflow-y-auto p-2" role="listbox">
                    <li v-if="!all.length" class="px-3 py-10 text-center text-sm text-ink-3">No matches for “{{ q }}”.</li>
                    <li v-for="(r, i) in all" :key="r.url + i" role="option" :aria-selected="i === active">
                        <p v-if="i === 0 || all[i - 1].group !== r.group" class="eyebrow px-3 pb-1.5 pt-3 text-ink-3">{{ r.group }}</p>
                        <button :class="i === active ? 'bg-brand-50 shadow-[inset_0_0_0_1px_color-mix(in_oklab,var(--color-brand-400)_25%,transparent)]' : ''" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors" @mouseenter="active = i" @click="go(r)">
                            <span v-if="r.icon" :class="i === active ? 'text-brand-600' : 'text-ink-3'" class="grid size-8 shrink-0 place-items-center rounded-lg bg-ink/[0.05]"><Icon :name="r.icon" :size="16" /></span>
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ r.title }}</span><span class="block truncate text-xs text-ink-3">{{ r.subtitle }}</span></span>
                            <Icon name="arrow-right" :size="14" :class="i === active ? 'text-brand-600 translate-x-0.5' : 'text-ink-3/60'" class="transition" />
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </Teleport>
</template>
