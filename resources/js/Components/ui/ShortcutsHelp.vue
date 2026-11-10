<script setup>
// Ctrl+/ (or ?) cheat-sheet of every keyboard shortcut the signed-in user can use.
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import { shortcuts, keyLabel, isMac } from '@/lib/shortcuts';
import { can } from '@/lib/nav';

const open = defineModel({ type: Boolean });
const user = computed(() => usePage().props.auth?.user);
const groups = computed(() => {
    const g = {};
    for (const s of shortcuts) if (!s.hidden && (!s.perm || can(user.value, s.perm))) (g[s.group] ??= []).push(s);
    return Object.entries(g);
});
</script>

<template>
    <Modal v-model="open" title="Keyboard shortcuts" :subtitle="isMac ? '⌘ or Ctrl both work on Mac' : 'Work from any page — even while typing in a field'" icon="bolt" max-width="max-w-3xl">
        <div class="grid gap-5 sm:grid-cols-2">
            <section v-for="[group, list] in groups" :key="group" :class="group === 'Go to' && 'sm:row-span-2'">
                <p class="eyebrow mb-2 text-brand-600">{{ group }}</p>
                <ul class="space-y-0.5">
                    <li v-for="s in list" :key="s.label + s.keys.join()" class="flex items-center justify-between gap-3 rounded-xl px-2 py-1.5 transition hover:bg-ink/[0.04]">
                        <span class="min-w-0 truncate text-[13px] text-ink-2">{{ s.label }}</span>
                        <span class="flex shrink-0 items-center gap-1">
                            <kbd v-for="k in s.keys" :key="k" class="min-w-6 rounded-md border border-line-strong bg-ink/[0.04] px-1.5 py-0.5 text-center font-mono text-[11px] font-semibold text-ink shadow-[inset_0_-1px_0_var(--color-line-strong)]">{{ keyLabel(k) }}</kbd>
                            <template v-if="s.alt"><span class="px-0.5 text-[11px] text-ink-3">or</span><kbd v-for="k in s.alt" :key="`a${k}`" class="min-w-6 rounded-md border border-line-strong bg-ink/[0.04] px-1.5 py-0.5 text-center font-mono text-[11px] font-semibold text-ink">{{ k }}</kbd></template>
                        </span>
                    </li>
                </ul>
            </section>
        </div>
        <p class="mt-5 rounded-xl border border-line bg-ink/[0.03] p-3 text-xs leading-relaxed text-ink-3">Chrome and Edge keep <b class="text-ink-2">Ctrl+N</b> for “new window”, so use <b class="text-ink-2">Alt+N</b> there for a new project. Inside a drawer, <b class="text-ink-2">Ctrl+S</b> or <b class="text-ink-2">Ctrl+Enter</b> saves it.</p>
    </Modal>
</template>
