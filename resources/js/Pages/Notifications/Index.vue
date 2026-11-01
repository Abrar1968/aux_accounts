<script setup>
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Button from '@/Components/ui/Button.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { dateTime, relative } from '@/lib/format';
import { computed } from 'vue';

const props = defineProps({ items: Object, filter: String });
const tab = computed({ get: () => props.filter, set: (v) => router.get('/notifications', { filter: v }, { preserveScroll: true }) });
const tones = { success: 'bg-positive', error: 'bg-negative', warning: 'bg-warning', info: 'bg-info' };
const open = (n) => router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true, onSuccess: () => n.data.url && router.visit(n.data.url) });
</script>

<template>
    <Head title="Notifications" />
    <PageHeader title="Notifications" subtitle="Reminders, postings and automation alerts">
        <Button variant="secondary" size="sm" href="/profile/notifications">Preferences</Button>
        <Button size="sm" @click="router.post('/notifications/read-all', {}, { preserveScroll: true })">Mark all read</Button>
    </PageHeader>
    <div class="mb-3"><Tabs v-model="tab" :tabs="[{ key: 'all', label: 'All' }, { key: 'unread', label: 'Unread' }]" /></div>
    <Card :padded="false">
        <EmptyState v-if="!items.data.length" title="You're all caught up" icon="bell" />
        <ul v-else class="divide-y divide-line">
            <li v-for="n in items.data" :key="n.id">
                <button class="flex w-full gap-3 px-4 py-3.5 text-left hover:bg-surface-2 sm:px-5" @click="open(n)">
                    <span :class="[tones[n.data.level] ?? 'bg-info', n.read_at && 'opacity-25']" class="mt-1.5 size-2.5 shrink-0 rounded-full" />
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-baseline justify-between gap-x-3"><span class="truncate text-sm font-semibold" :class="n.read_at && 'font-medium text-ink-2'">{{ n.data.title }}</span><span class="text-xs text-ink-3" :title="dateTime(n.created_at)">{{ relative(n.created_at) }}</span></span>
                        <span class="block text-sm text-ink-2">{{ n.data.message }}</span>
                        <ul v-if="n.data.meta?.lines?.length" class="mt-1 list-inside list-disc text-xs text-ink-3"><li v-for="l in n.data.meta.lines.slice(0, 5)" :key="l" class="truncate">{{ l }}</li></ul>
                    </span>
                </button>
            </li>
        </ul>
        <Pagination :page="items" />
    </Card>
</template>
