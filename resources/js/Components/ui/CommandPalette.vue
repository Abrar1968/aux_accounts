<script setup>
// Ctrl/⌘ K: global search (/ajax/search) + quick navigation, keyboard driven.
import { computed, nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { getJson } from '@/Composables/useJson';
import { navigation, can } from '@/lib/nav';
import { usePermissions } from '@/Composables/usePermissions';

const open = defineModel({ type: Boolean });
const q = ref('');
const results = ref([]);
const active = ref(0);
const input = ref(null);
const { user } = usePermissions();
const pages = computed(() => navigation.flatMap((s) => s.items).filter((i) => can(user.value, i.perm))
    .filter((i) => !q.value || i.label.toLowerCase().includes(q.value.toLowerCase()))
    .map((i) => ({ group: 'Go to', title: i.label, subtitle: i.href, url: i.href })));
const all = computed(() => [...results.value, ...pages.value].slice(0, 30));
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
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center bg-ink/40 p-0 backdrop-blur-[2px] sm:p-4 sm:pt-[12vh]" @click.self="open = false">
            <div class="card flex h-full w-full flex-col overflow-hidden shadow-[var(--shadow-pop)] sm:h-auto sm:max-h-[70vh] sm:max-w-xl" role="dialog" aria-label="Search">
                <div class="flex items-center gap-3 border-b border-line px-4">
                    <Icon name="search" class="text-ink-3" />
                    <input ref="input" v-model="q" class="h-14 flex-1 bg-transparent text-base outline-none" placeholder="Search projects, payments, expenses, leads…" @keydown="onKey" />
                    <button class="rounded border border-line px-1.5 py-0.5 text-xs text-ink-3" @click="open = false">Esc</button>
                </div>
                <ul class="flex-1 overflow-y-auto p-2" role="listbox">
                    <li v-if="!all.length" class="px-3 py-10 text-center text-sm text-ink-3">No matches for “{{ q }}”.</li>
                    <li v-for="(r, i) in all" :key="r.url + i" role="option" :aria-selected="i === active">
                        <p v-if="i === 0 || all[i - 1].group !== r.group" class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wider text-ink-3">{{ r.group }}</p>
                        <button :class="i === active ? 'bg-brand-50' : ''" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left" @mouseenter="active = i" @click="go(r)">
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ r.title }}</span><span class="block truncate text-xs text-ink-3">{{ r.subtitle }}</span></span>
                            <Icon name="external" :size="14" class="text-ink-3" />
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </Teleport>
</template>
