<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/ui/Icon.vue';
import ToastStack from '@/Components/ui/ToastStack.vue';
import { navigation, can } from '@/lib/nav';
import { useToasts } from '@/Composables/useToasts';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const unread = ref(page.props.notifications?.unread ?? 0);
const drawer = ref(false);
const dark = ref(document.documentElement.classList.contains('dark'));
const { push } = useToasts();

const sections = computed(() =>
    navigation
        .map((s) => ({ ...s, items: s.items.filter((i) => !user.value || can(user.value, i.perm)) }))
        .filter((s) => s.items.length),
);
const isActive = (href) => (href === '/' ? page.url === '/' : page.url.startsWith(href));

function toggleTheme() {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
    try { localStorage.setItem('theme', dark.value ? 'dark' : 'light'); } catch (e) {}
}

// Realtime: ledger / CRM events from Pusher → toast + soft reload of current page props.
let channel;
onMounted(() => {
    router.on('navigate', () => (drawer.value = false));
    if (!window.Echo || !user.value) return;
    channel = window.Echo.private('auxtech')
        .listen('.ledger.changed', (e) => { push({ type: 'info', title: 'Ledger updated', message: `${e.code} posted` }); router.reload({ preserveScroll: true }); })
        .listen('.lead.updated', () => router.reload({ only: ['leads'], preserveScroll: true }))
        .listen('.automation.finished', (e) => push({ type: 'success', title: 'Automation', message: `${e.command} finished` }));
    window.Echo.private(`App.Models.User.${user.value.id}`).notification((n) => { unread.value++; push({ type: n.level ?? 'info', title: n.title, message: n.message }); });
});
onBeforeUnmount(() => channel && window.Echo?.leave('auxtech'));
</script>

<template>
    <div class="min-h-full lg:grid lg:grid-cols-[264px_1fr]">
        <!-- Sidebar: fixed on desktop, slide-over drawer on mobile -->
        <div v-if="drawer" class="fixed inset-0 z-30 bg-ink/40 backdrop-blur-sm lg:hidden" @click="drawer = false" />
        <aside :class="drawer ? 'translate-x-0' : '-translate-x-full'" class="no-print fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-line bg-surface transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0">
            <div class="flex h-16 items-center gap-2.5 px-5">
                <div class="grid size-8 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white">A</div>
                <div class="leading-tight"><p class="text-sm font-semibold">Auxtech Ops</p><p class="text-xs text-ink-3">Accounting · CRM</p></div>
                <button class="ml-auto rounded-md p-1.5 text-ink-3 hover:bg-surface-2 lg:hidden" aria-label="Close menu" @click="drawer = false"><Icon name="x" /></button>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 pb-6" aria-label="Main">
                <div v-for="s in sections" :key="s.section" class="mt-5 first:mt-2">
                    <p class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-3">{{ s.section }}</p>
                    <Link v-for="i in s.items" :key="i.href" :href="i.href"
                        :class="isActive(i.href) ? 'bg-brand-50 text-brand-700 dark:text-brand-100' : 'text-ink-2 hover:bg-surface-2 hover:text-ink'"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors">
                        <Icon :name="i.icon" />{{ i.label }}
                    </Link>
                </div>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-col">
            <header class="no-print sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-surface/85 px-4 backdrop-blur sm:px-6">
                <button class="rounded-md p-2 text-ink-2 hover:bg-surface-2 lg:hidden" aria-label="Open menu" @click="drawer = true"><Icon name="menu" /></button>
                <button class="flex h-9 min-w-0 flex-1 items-center gap-2 rounded-lg border border-line bg-surface-2 px-3 text-sm text-ink-3 sm:max-w-md" @click="$emit('search')">
                    <Icon name="search" :size="16" /><span class="truncate">Search projects, payments, leads…</span>
                    <kbd class="ml-auto hidden rounded border border-line px-1.5 text-[11px] sm:inline">Ctrl K</kbd>
                </button>
                <div class="ml-auto flex items-center gap-1">
                    <button class="rounded-lg p-2 text-ink-2 hover:bg-surface-2" :aria-label="dark ? 'Light mode' : 'Dark mode'" @click="toggleTheme"><Icon :name="dark ? 'sun' : 'moon'" /></button>
                    <Link href="/notifications" class="relative rounded-lg p-2 text-ink-2 hover:bg-surface-2" aria-label="Notifications">
                        <Icon name="bell" />
                        <span v-if="unread" class="absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-negative px-1 text-[10px] font-bold leading-4 text-white">{{ unread > 9 ? '9+' : unread }}</span>
                    </Link>
                    <div v-if="user" class="ml-1 hidden items-center gap-2 sm:flex">
                        <div class="grid size-8 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ user.name.slice(0, 2).toUpperCase() }}</div>
                    </div>
                </div>
            </header>
            <main class="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 sm:px-6 lg:px-8"><slot /></main>
        </div>
        <ToastStack />
    </div>
</template>
