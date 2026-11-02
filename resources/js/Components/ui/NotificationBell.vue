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
    <Dropdown width="w-80">
        <template #trigger>
            <button class="relative rounded-lg p-2 text-ink-2 hover:bg-surface-2" aria-label="Notifications" @click="load">
                <Icon name="bell" />
                <span v-if="unread" class="absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-negative px-1 text-[10px] font-bold leading-4 text-white">{{ unread > 9 ? '9+' : unread }}</span>
            </button>
        </template>
        <div class="flex items-center justify-between px-3 py-2"><p class="text-sm font-semibold">Notifications</p><Link href="/notifications" class="text-xs font-medium text-brand-600">View all</Link></div>
        <p v-if="loading && !items.length" class="px-3 py-6 text-center text-sm text-ink-3">Loading…</p>
        <p v-else-if="!items.length" class="px-3 py-6 text-center text-sm text-ink-3">You're all caught up.</p>
        <ul v-else class="max-h-96 overflow-y-auto">
            <li v-for="n in items" :key="n.id">
                <button class="flex w-full gap-3 rounded-md px-3 py-2.5 text-left hover:bg-surface-2" @click.stop="openItem(n)">
                    <span :class="[tones[n.data.level] ?? 'bg-info', n.read_at && 'opacity-30']" class="mt-1.5 size-2 shrink-0 rounded-full" />
                    <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ n.data.title }}</span><span class="line-clamp-2 block text-xs text-ink-2">{{ n.data.message }}</span><span class="text-[11px] text-ink-3">{{ relative(n.created_at) }}</span></span>
                </button>
            </li>
        </ul>
    </Dropdown>
</template>
