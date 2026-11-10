<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Dropdown from './Dropdown.vue';
import Icon from './Icon.vue';
import { getJson } from '@/Composables/useJson';
import { relative } from '@/lib/format';

const unread = defineModel('unread', { type: Number, default: 0 });
const items = ref([]);
const loading = ref(false);
async function load() {
    loading.value = true;
    try { const r = await getJson('/ajax/notifications'); items.value = r.items; unread.value = r.unread; } finally { loading.value = false; }
}
const tones = { success: 'bg-positive', error: 'bg-negative', warning: 'bg-warning', info: 'bg-info' };
function openItem(n) { router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true, onSuccess: () => n.data.url && router.visit(n.data.url) }); }
</script>

<template>
    <Dropdown width="w-[22rem]">
        <template #trigger>
            <button class="group relative grid size-10 place-items-center rounded-xl text-ink-2 transition hover:bg-ink/5 hover:text-ink" aria-label="Notifications" @click="load">
                <Icon name="bell" class="transition group-hover:rotate-12" />
                <span v-if="unread" class="absolute right-1.5 top-1.5 grid min-w-[18px] place-items-center rounded-full bg-brand-gradient px-1 font-mono text-[10px] font-bold leading-[18px] text-on-brand shadow-[0_0_10px_var(--color-brand-400)] ring-2 ring-canvas">{{ unread > 9 ? '9+' : unread }}</span>
            </button>
        </template>
        <div class="flex items-center justify-between border-b border-line px-3 pb-2.5 pt-1.5"><p class="text-sm font-bold">Notifications</p><Link href="/notifications" class="text-xs font-semibold text-brand-600 hover:underline">View all</Link></div>
        <p v-if="loading && !items.length" class="px-3 py-6 text-center text-sm text-ink-3">Loading…</p>
        <p v-else-if="!items.length" class="px-3 py-6 text-center text-sm text-ink-3">You're all caught up.</p>
        <ul v-else class="max-h-96 overflow-y-auto">
            <li v-for="n in items" :key="n.id">
                <button class="flex w-full gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-ink/[0.05]" :class="!n.read_at && 'bg-brand-50'" @click.stop="openItem(n)">
                    <span :class="[tones[n.data.level] ?? 'bg-info', n.read_at && 'opacity-30']" class="mt-1.5 size-2 shrink-0 rounded-full" />
                    <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ n.data.title }}</span><span class="line-clamp-2 block text-xs text-ink-2">{{ n.data.message }}</span><span class="text-[11px] text-ink-3">{{ relative(n.created_at) }}</span></span>
                </button>
            </li>
        </ul>
    </Dropdown>
</template>
