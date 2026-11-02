<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/ui/Icon.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ToastStack from '@/Components/ui/ToastStack.vue';
import NotificationBell from '@/Components/ui/NotificationBell.vue';
import CommandPalette from '@/Components/ui/CommandPalette.vue';
import Dropdown from '@/Components/ui/Dropdown.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import { navigation, can, findNav, quickCreate } from '@/lib/nav';
import { useHotkey } from '@/Composables/useHotkey';
import { useRealtime } from '@/Composables/useRealtime';
import { setPdfBrand } from '@/lib/pdf';

const page = usePage();
const brand = computed(() => page.props.brand ?? {});
watch(brand, (b) => {
    setPdfBrand(b); // letterhead for every PDF export
    const icon = document.getElementById('app-favicon'); // logo changes show in the tab without reload
    if (icon && b?.favicon && icon.getAttribute('href') !== b.favicon) icon.setAttribute('href', b.favicon);
    if (b?.accent) document.documentElement.dataset.accent = b.accent; // Settings → Branding accent colour
}, { immediate: true });

const user = computed(() => page.props.auth?.user);
const unread = ref(page.props.notifications?.unread ?? 0);
watch(() => page.props.notifications?.unread, (v) => (unread.value = v ?? 0));
const drawer = ref(false);
const palette = ref(false);
const dark = ref(document.documentElement.classList.contains('dark'));

const sections = computed(() =>
    navigation.map((s) => ({ ...s, items: s.items.filter((i) => can(user.value, i.perm)) })).filter((s) => s.items.length));
const creates = computed(() => quickCreate.filter((i) => can(user.value, i.perm)));
const current = computed(() => findNav(page.url));
const isActive = (href) => current.value?.href === href;

// Collapsible sections (remembered per browser); the section of the open page is always expanded.
const collapsed = ref((() => { try { return JSON.parse(localStorage.getItem('nav.collapsed') || '[]'); } catch { return []; } })());
const isOpen = (s) => !collapsed.value.includes(s.section) || s.items.some((i) => isActive(i.href));
function toggleSection(s) {
    collapsed.value = collapsed.value.includes(s.section) ? collapsed.value.filter((x) => x !== s.section) : [...collapsed.value, s.section];
    try { localStorage.setItem('nav.collapsed', JSON.stringify(collapsed.value)); } catch { /* private mode */ }
}

function toggleTheme() {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
    try { localStorage.setItem('theme', dark.value ? 'dark' : 'light'); } catch { /* private mode */ }
}
useHotkey('k', () => (palette.value = true), { meta: true });
useHotkey('/', () => (palette.value = true));
useRealtime(user, () => unread.value++);
onMounted(() => router.on('navigate', () => (drawer.value = false)));
const roleLabel = computed(() => ({ owner: 'Owner', accountant: 'Accountant', sales_manager: 'Sales manager', caller: 'Caller', viewer: 'Viewer' })[user.value?.role] ?? user.value?.role);
</script>

<template>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">Skip to content</a>
    <div class="app-shell min-h-full lg:grid lg:grid-cols-[272px_minmax(0,1fr)]">
        <Transition enter-from-class="opacity-0" enter-active-class="transition" leave-to-class="opacity-0" leave-active-class="transition">
            <div v-if="drawer" class="fixed inset-0 z-30 bg-[#05070f]/60 backdrop-blur-sm lg:hidden" @click="drawer = false" />
        </Transition>

        <!-- Sidebar -->
        <aside :class="drawer ? 'translate-x-0' : '-translate-x-full'"
            class="no-print fixed inset-y-0 left-0 z-40 flex w-[18rem] max-w-[86vw] flex-col overflow-hidden bg-gradient-to-b from-side-2 via-side to-side text-slate-300 transition-transform duration-300 lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:max-w-none lg:translate-x-0">
            <div class="pointer-events-none absolute -left-24 -top-24 size-72 rounded-full bg-brand-500/25 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -bottom-32 -right-24 size-72 rounded-full bg-brand-alt/15 blur-3xl" aria-hidden="true" />

            <div class="relative flex h-[72px] shrink-0 items-center gap-3 px-5">
                <Link href="/" class="flex min-w-0 items-center gap-3">
                    <span v-if="brand.logo" class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-white p-1 shadow-lg shadow-black/30"><img :src="brand.logo.png" alt="" class="max-h-full max-w-full object-contain" /></span>
                    <span v-else class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-gradient text-base font-extrabold text-white shadow-lg shadow-brand-600/40 ring-1 ring-inset ring-white/20">{{ (brand.company || 'A').charAt(0) }}</span>
                    <span class="min-w-0 leading-tight"><span class="block truncate text-[15px] font-bold tracking-[-0.01em] text-white">{{ brand.company || 'Auxtech' }}</span><span class="block text-[11px] font-medium text-slate-400">Accounting · CRM</span></span>
                </Link>
                <button class="ml-auto rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Close menu" @click="drawer = false"><Icon name="x" /></button>
            </div>

            <nav class="relative flex-1 overflow-y-auto px-3 pb-4 [scrollbar-color:rgb(255_255_255/0.15)_transparent]" aria-label="Main">
                <div v-for="s in sections" :key="s.section" class="mt-4 first:mt-1">
                    <button class="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.14em] text-slate-500 hover:text-slate-300" :aria-expanded="isOpen(s)" @click="toggleSection(s)">
                        {{ s.section }}<Icon name="chevron-down" :size="14" :class="!isOpen(s) && '-rotate-90'" class="transition-transform" />
                    </button>
                    <div v-show="isOpen(s)" class="mt-0.5 space-y-0.5">
                        <Link v-for="i in s.items" :key="i.href" :href="i.href" :aria-current="isActive(i.href) ? 'page' : undefined"
                            :class="isActive(i.href) ? 'bg-white/[0.09] text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.06)]' : 'text-slate-400 hover:bg-white/[0.05] hover:text-slate-100'"
                            class="group relative flex items-center gap-3 rounded-[10px] px-3 py-2 text-[13.5px] font-medium transition-colors">
                            <span v-if="isActive(i.href)" class="absolute inset-y-1.5 left-0 w-[3px] rounded-r-full bg-gradient-to-b from-brand-400 to-brand-alt shadow-[0_0_12px_var(--color-brand-500)]" aria-hidden="true" />
                            <Icon :name="i.icon" :size="18" :class="isActive(i.href) ? 'text-brand-300' : 'text-slate-500 group-hover:text-slate-300'" />{{ i.label }}
                        </Link>
                    </div>
                </div>
            </nav>

            <div v-if="user" class="relative m-3 flex items-center gap-3 rounded-2xl border border-white/[0.07] bg-white/[0.04] p-3">
                <Avatar :name="user.name" size="md" />
                <div class="min-w-0 flex-1 leading-tight"><p class="truncate text-sm font-semibold text-white">{{ user.name }}</p><p class="truncate text-[11px] text-slate-400">{{ roleLabel }}</p></div>
                <Link href="/logout" method="post" as="button" class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white" aria-label="Sign out" title="Sign out"><Icon name="logout" :size="18" /></Link>
            </div>
        </aside>

        <div class="flex min-w-0 flex-col">
            <!-- Top bar -->
            <header class="no-print sticky top-0 z-20 border-b border-line/70 bg-surface/70 backdrop-blur-xl supports-[backdrop-filter]:bg-surface/60">
                <div class="mx-auto flex h-16 w-full max-w-[1480px] items-center gap-2 px-3 sm:gap-3 sm:px-6 lg:px-8">
                    <button class="rounded-lg p-2 text-ink-2 hover:bg-surface-2 lg:hidden" aria-label="Open menu" @click="drawer = true"><Icon name="menu" /></button>
                    <nav v-if="current" class="hidden min-w-0 items-center gap-1.5 text-[13px] md:flex" aria-label="Breadcrumb">
                        <span class="text-ink-3">{{ current.section }}</span><Icon name="chevron-right" :size="14" class="text-ink-3" />
                        <Link :href="current.href" class="truncate font-semibold text-ink">{{ current.label }}</Link>
                    </nav>
                    <button class="group ml-auto flex h-10 min-w-0 flex-1 items-center gap-2.5 rounded-xl border border-line bg-surface px-3 text-sm text-ink-3 shadow-[var(--shadow-xs)] transition hover:border-line-strong sm:w-64 sm:flex-none lg:w-80" @click="palette = true">
                        <Icon name="search" :size="16" /><span class="truncate">Search anything…</span>
                        <kbd class="ml-auto hidden rounded-md border border-line bg-surface-2 px-1.5 py-0.5 font-sans text-[10.5px] font-semibold text-ink-3 sm:inline">Ctrl K</kbd>
                    </button>
                    <div class="flex items-center gap-1">
                        <Dropdown v-if="creates.length" width="w-56">
                            <template #trigger><button class="hidden h-10 items-center gap-1.5 rounded-xl bg-brand-gradient px-3.5 text-sm font-semibold text-white shadow-[var(--shadow-brand)] ring-1 ring-inset ring-white/15 transition hover:brightness-110 sm:inline-flex"><Icon name="plus" :size="16" />Create</button></template>
                            <p class="px-3 pb-1 pt-1.5 text-[10.5px] font-bold uppercase tracking-[0.12em] text-ink-3">Quick create</p>
                            <MenuItem v-for="c in creates" :key="c.href" :href="c.href" :icon="c.icon">{{ c.label }}</MenuItem>
                        </Dropdown>
                        <button class="grid size-10 place-items-center rounded-xl text-ink-2 transition hover:bg-surface-2 hover:text-ink" :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'" @click="toggleTheme"><Icon :name="dark ? 'sun' : 'moon'" /></button>
                        <NotificationBell v-model:unread="unread" />
                        <Dropdown v-if="user" width="w-64">
                            <template #trigger><button class="ml-1 rounded-full ring-2 ring-transparent transition hover:ring-brand-200" :aria-label="`Account menu for ${user.name}`"><Avatar :name="user.name" size="md" /></button></template>
                            <div class="flex items-center gap-3 border-b border-line px-3 pb-3 pt-2"><Avatar :name="user.name" size="lg" /><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ user.name }}</p><p class="truncate text-xs text-ink-3">{{ user.email }}</p></div></div>
                            <div class="pt-1.5">
                                <MenuItem href="/profile" icon="user">Profile</MenuItem>
                                <MenuItem href="/profile/notifications" icon="bell">Notification settings</MenuItem>
                                <MenuItem v-if="can(user, 'settings.manage')" href="/settings" icon="cog">Settings</MenuItem>
                                <Link href="/logout" method="post" as="button" class="mt-1 flex w-full items-center gap-2.5 rounded-lg border-t border-line px-3 py-2 text-left text-sm font-medium text-negative hover:bg-negative/10"><Icon name="logout" :size="16" />Sign out</Link>
                            </div>
                        </Dropdown>
                    </div>
                </div>
            </header>
            <main id="main" class="mx-auto w-full min-w-0 max-w-[1480px] flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-8"><slot /></main>
        </div>
        <ToastStack />
        <CommandPalette v-model="palette" />
    </div>
</template>
