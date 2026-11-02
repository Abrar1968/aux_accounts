<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/ui/Icon.vue';
import ToastStack from '@/Components/ui/ToastStack.vue';
import NotificationBell from '@/Components/ui/NotificationBell.vue';
import CommandPalette from '@/Components/ui/CommandPalette.vue';
import Dropdown from '@/Components/ui/Dropdown.vue';
import { navigation, can } from '@/lib/nav';
import { initials } from '@/lib/format';
import { useHotkey } from '@/Composables/useHotkey';
import { useRealtime } from '@/Composables/useRealtime';
import { setPdfBrand } from '@/lib/pdf';

const page = usePage();
watch(() => page.props.brand, (b) => {
    setPdfBrand(b); // letterhead for every PDF export
    const icon = document.getElementById('app-favicon'); // logo changes show in the tab without reload
    if (icon && b?.favicon && icon.getAttribute('href') !== b.favicon) icon.setAttribute('href', b.favicon);
}, { immediate: true });
const user = computed(() => page.props.auth?.user);
const unread = ref(page.props.notifications?.unread ?? 0);
watch(() => page.props.notifications?.unread, (v) => (unread.value = v ?? 0));
const drawer = ref(false);
const palette = ref(false);
const dark = ref(document.documentElement.classList.contains('dark'));

const sections = computed(() =>
    navigation.map((s) => ({ ...s, items: s.items.filter((i) => can(user.value, i.perm)) })).filter((s) => s.items.length));
const path = computed(() => page.url.split('?')[0]);
const isActive = (href) => (href === '/' ? path.value === '/' : href === '/crm' ? path.value === '/crm' : path.value.startsWith(href));

function toggleTheme() {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
    try { localStorage.setItem('theme', dark.value ? 'dark' : 'light'); } catch (e) { /* private mode */ }
}
useHotkey('k', () => (palette.value = true), { meta: true });
useHotkey('/', () => (palette.value = true));
useRealtime(user, () => unread.value++);
onMounted(() => router.on('navigate', () => (drawer.value = false)));
</script>

<template>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">Skip to content</a>
    <div class="app-shell min-h-full lg:grid lg:grid-cols-[256px_minmax(0,1fr)]">
        <div v-if="drawer" class="fixed inset-0 z-30 bg-ink/40 backdrop-blur-sm lg:hidden" @click="drawer = false" />
        <aside :class="drawer ? 'translate-x-0' : '-translate-x-full'" class="no-print fixed inset-y-0 left-0 z-40 flex w-[17rem] max-w-[85vw] flex-col border-r border-line bg-surface transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:max-w-none lg:translate-x-0">
            <div class="flex h-16 shrink-0 items-center gap-2.5 px-5">
                <div class="grid size-8 place-items-center rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold text-white shadow-sm">A</div>
                <div class="leading-tight"><p class="text-sm font-semibold">Auxtech Ops</p><p class="text-xs text-ink-3">Accounting · CRM</p></div>
                <button class="ml-auto rounded-md p-1.5 text-ink-3 hover:bg-surface-2 lg:hidden" aria-label="Close menu" @click="drawer = false"><Icon name="x" /></button>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 pb-6" aria-label="Main">
                <div v-for="s in sections" :key="s.section" class="mt-5 first:mt-1">
                    <p class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-3">{{ s.section }}</p>
                    <Link v-for="i in s.items" :key="i.href" :href="i.href" :aria-current="isActive(i.href) ? 'page' : undefined"
                        :class="isActive(i.href) ? 'bg-brand-50 text-brand-700 dark:text-white' : 'text-ink-2 hover:bg-surface-2 hover:text-ink'"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors">
                        <Icon :name="i.icon" />{{ i.label }}
                    </Link>
                </div>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-col">
            <header class="no-print sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-line bg-surface/85 px-3 backdrop-blur sm:gap-3 sm:px-6">
                <button class="rounded-md p-2 text-ink-2 hover:bg-surface-2 lg:hidden" aria-label="Open menu" @click="drawer = true"><Icon name="menu" /></button>
                <button class="flex h-9 min-w-0 flex-1 items-center gap-2 rounded-lg border border-line bg-surface-2 px-3 text-sm text-ink-3 sm:max-w-md" @click="palette = true">
                    <Icon name="search" :size="16" /><span class="truncate">Search…</span>
                    <kbd class="ml-auto hidden rounded border border-line px-1.5 text-[11px] sm:inline">Ctrl K</kbd>
                </button>
                <div class="ml-auto flex items-center gap-0.5">
                    <button class="rounded-lg p-2 text-ink-2 hover:bg-surface-2" :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'" @click="toggleTheme"><Icon :name="dark ? 'sun' : 'moon'" /></button>
                    <NotificationBell v-model:unread="unread" />
                    <Dropdown v-if="user" width="w-60">
                        <template #trigger><button class="ml-1 grid size-8 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700" :aria-label="`Account menu for ${user.name}`">{{ initials(user.name) }}</button></template>
                        <div class="border-b border-line px-3 py-2"><p class="truncate text-sm font-semibold">{{ user.name }}</p><p class="truncate text-xs text-ink-3">{{ user.email }}</p></div>
                        <Link href="/profile" class="block rounded-md px-3 py-2 text-sm hover:bg-surface-2">Profile</Link>
                        <Link href="/profile/notifications" class="block rounded-md px-3 py-2 text-sm hover:bg-surface-2">Notification settings</Link>
                        <Link href="/logout" method="post" as="button" class="block w-full rounded-md px-3 py-2 text-left text-sm text-negative hover:bg-surface-2">Sign out</Link>
                    </Dropdown>
                </div>
            </header>
            <main id="main" class="mx-auto w-full min-w-0 max-w-[1440px] flex-1 px-4 py-5 sm:px-6 sm:py-7 lg:px-8"><slot /></main>
        </div>
        <ToastStack />
        <CommandPalette v-model="palette" />
    </div>
</template>
