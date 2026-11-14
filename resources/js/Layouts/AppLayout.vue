<script setup>
// "Command glass" shell: module rail (Accounting · Lead CRM · System) → frosted section panel → page.
// On phones the rail + panel slide in together as one drawer.
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/ui/Icon.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import ToastStack from '@/Components/ui/ToastStack.vue';
import NotificationBell from '@/Components/ui/NotificationBell.vue';
import CommandPalette from '@/Components/ui/CommandPalette.vue';
import ShortcutsHelp from '@/Components/ui/ShortcutsHelp.vue';
import Dropdown from '@/Components/ui/Dropdown.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import { navigation, modules, can, findNav, quickCreate } from '@/lib/nav';
import { useHotkey } from '@/Composables/useHotkey';
import { useRealtime } from '@/Composables/useRealtime';
import { setPdfBrand } from '@/lib/pdf';
import { match, submitOpenForm, focusPageSearch, shortcutFor, keyLabel } from '@/lib/shortcuts';

const page = usePage();
const brand = computed(() => page.props.brand ?? {});
const appName = computed(() => page.props.app?.name ?? 'Auxtech Accounts');
watch(brand, (b) => {
    setPdfBrand(b); // letterhead for every PDF export
    const icon = document.getElementById('app-favicon'); // logo changes show in the tab without reload
    if (icon && b?.favicon && icon.getAttribute('href') !== b.favicon) icon.setAttribute('href', b.favicon);
    if (b?.accent) document.documentElement.dataset.accent = b.accent; // Settings → Branding accent colour
}, { immediate: true });

const user = computed(() => page.props.auth?.user);
const unread = ref(page.props.notifications?.unread ?? 0);
const inboxUnread = computed(() => page.props.inbox?.unread ?? 0);
watch(() => page.props.notifications?.unread, (v) => (unread.value = v ?? 0));
const drawer = ref(false);
const palette = ref(false);
const dark = ref(document.documentElement.classList.contains('dark'));

const sections = computed(() =>
    navigation.map((s) => ({ ...s, items: s.items.filter((i) => can(user.value, i.perm)) })).filter((s) => s.items.length));
const mods = computed(() => modules.filter((m) => sections.value.some((s) => s.module === m.key)));
const creates = computed(() => quickCreate.filter((i) => can(user.value, i.perm)));
const current = computed(() => findNav(page.url));
const isActive = (href) => current.value?.href === href;
const currentModule = computed(() => current.value?.module ?? mods.value[0]?.key ?? 'accounting');

// The panel follows the open page; clicking a rail icon previews another module without navigating.
const shown = ref(currentModule.value);
watch(currentModule, (m) => (shown.value = m));
const shownModule = computed(() => modules.find((m) => m.key === shown.value) ?? modules[0]);
const panel = computed(() => sections.value.filter((s) => s.module === shown.value));
const moduleOf = (key) => modules.find((m) => m.key === key);

function toggleTheme() {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
    try { localStorage.setItem('theme', dark.value ? 'dark' : 'light'); } catch { /* private mode */ }
}
useHotkey('/', () => (palette.value = true));
const help = ref(false);

// Global Ctrl shortcuts (lib/shortcuts.js): create records, jump to sections, save forms.
function onShortcut(e) {
    if (e.key === '?' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target?.tagName) && !e.target?.isContentEditable) { e.preventDefault(); help.value = true; return; }
    const s = match(e);
    if (!s || (s.perm && !can(user.value, s.perm))) return;
    if (s.action === 'find') { if (focusPageSearch()) e.preventDefault(); return; }
    e.preventDefault();
    if (s.action === 'palette') palette.value = true;
    else if (s.action === 'help') help.value = !help.value;
    else if (s.action === 'save') submitOpenForm();
    else if (s.href) { palette.value = false; help.value = false; router.visit(s.href); }
}
const hint = (href) => { const s = shortcutFor(href); return s ? s.keys.map(keyLabel).join('+') : null; };
useRealtime(user, () => unread.value++);

// Spotlight hover: cards with .spotlight get a soft amber glow that follows the pointer.
function track(e) {
    const el = e.target instanceof Element ? e.target.closest('.spotlight') : null;
    if (!el) return;
    const r = el.getBoundingClientRect();
    el.style.setProperty('--mx', `${e.clientX - r.left}px`);
    el.style.setProperty('--my', `${e.clientY - r.top}px`);
}
let off;
onMounted(() => {
    off = router.on('navigate', () => (drawer.value = false));
    window.addEventListener('pointermove', track, { passive: true });
    window.addEventListener('keydown', onShortcut);
});
onBeforeUnmount(() => { off?.(); window.removeEventListener('pointermove', track); window.removeEventListener('keydown', onShortcut); });
const roleLabel = computed(() => ({ owner: 'Owner', accountant: 'Accountant', sales_manager: 'Sales manager', caller: 'Caller', viewer: 'Viewer' })[user.value?.role] ?? user.value?.role);
const greetingDate = computed(() => new Date().toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }));
</script>

<template>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">Skip to content</a>
    <div class="app-shell relative isolate min-h-dvh lg:grid lg:grid-cols-[76px_248px_minmax(0,1fr)]">
        <!-- ambient light behind the glass -->
        <div class="no-print pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
            <div class="absolute -left-48 -top-40 size-[40rem] rounded-full bg-brand-400/[0.13] blur-[110px] [animation:drift_26s_ease-in-out_infinite]" />
            <div class="absolute -right-40 top-1/4 size-[36rem] rounded-full bg-info/[0.12] blur-[120px] [animation:drift_32s_ease-in-out_infinite_reverse]" />
            <div class="absolute bottom-[-20%] left-1/3 size-[30rem] rounded-full bg-brand-alt/[0.08] blur-[120px] [animation:drift_38s_ease-in-out_infinite]" />
            <div class="absolute inset-0 [background-image:linear-gradient(var(--grid-line)_1px,transparent_1px),linear-gradient(90deg,var(--grid-line)_1px,transparent_1px)] [background-size:56px_56px] [mask-image:radial-gradient(ellipse_at_top,black_20%,transparent_75%)]" />
        </div>

        <Transition enter-from-class="opacity-0" enter-active-class="transition" leave-to-class="opacity-0" leave-active-class="transition">
            <div v-if="drawer" class="fixed inset-0 z-30 bg-[#030612]/60 backdrop-blur-sm lg:hidden" @click="drawer = false" />
        </Transition>

        <!-- Rail + section panel (one drawer on phones, two grid columns on desktop) -->
        <div :class="drawer ? 'translate-x-0' : '-translate-x-full'" class="no-print fixed inset-y-0 left-0 z-40 flex w-[20rem] max-w-[90vw] transition-transform duration-300 ease-[cubic-bezier(.16,1,.3,1)] lg:contents">
            <!-- module rail: always midnight -->
            <aside class="relative flex w-[76px] shrink-0 flex-col items-center gap-1.5 border-r border-white/[0.07] bg-[var(--rail-bg)] py-4 text-[#8b94ab] backdrop-blur-xl lg:sticky lg:top-0 lg:h-dvh" aria-label="Modules">
                <Link href="/" class="group mb-4 grid size-11 place-items-center" :title="appName">
                    <span v-if="brand.mark" class="grid size-11 place-items-center overflow-hidden rounded-[14px] bg-white p-1.5 shadow-[0_8px_24px_-6px_rgb(0_0_0/0.6)] ring-1 ring-white/20 transition group-hover:scale-105 group-hover:shadow-[0_0_0_4px_rgb(255_180_84/0.18)]"><img :src="brand.mark" alt="" class="max-h-full max-w-full object-contain" /></span>
                    <span v-else class="grid size-11 place-items-center rounded-[14px] bg-brand-gradient text-lg font-extrabold text-on-brand shadow-[var(--shadow-brand)] transition group-hover:scale-105">{{ (appName || 'A').charAt(0) }}</span>
                </Link>
                <button v-for="m in mods" :key="m.key" type="button" :aria-pressed="shown === m.key" :title="m.label" @click="shown = m.key"
                    :class="shown === m.key ? 'text-[#ffc978]' : 'hover:text-[#e8ecf7]'" class="group relative flex w-full flex-col items-center gap-1 py-1.5 transition-colors">
                    <span v-if="currentModule === m.key" class="absolute left-0 top-1/2 h-7 w-[3px] -translate-y-1/2 rounded-r-full bg-[#ffb454] shadow-[0_0_14px_#ffb454]" aria-hidden="true" />
                    <span :class="shown === m.key ? 'bg-[#ffb454]/[0.14] ring-[#ffb454]/35' : 'ring-transparent group-hover:bg-white/[0.07]'" class="grid size-11 place-items-center rounded-2xl ring-1 ring-inset transition-all duration-200 group-hover:-translate-y-0.5">
                        <Icon :name="m.icon" :size="20" />
                    </span>
                    <span v-if="m.key === 'system' && inboxUnread" class="absolute right-4 top-1 size-2.5 rounded-full bg-[#ffb454] shadow-[0_0_8px_#ffb454] ring-2 ring-[#070c1d]" :title="`${inboxUnread} unread in Inbox`" />
                    <span class="font-mono text-[9px] font-medium uppercase tracking-[0.12em]">{{ m.key === 'accounting' ? 'Books' : m.key === 'crm' ? 'CRM' : 'System' }}</span>
                </button>
                <div class="mt-auto flex flex-col items-center gap-1.5">
                    <button class="grid size-10 place-items-center rounded-xl transition hover:bg-white/[0.08] hover:text-[#e8ecf7]" aria-label="Keyboard shortcuts" :title="`Keyboard shortcuts (${keyLabel('Ctrl')}+/)`" @click="help = true"><Icon name="bolt" :size="19" /></button>
                    <button class="grid size-10 place-items-center rounded-xl transition hover:bg-white/[0.08] hover:text-[#e8ecf7]" :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'" :title="dark ? 'Light mode' : 'Dark mode'" @click="toggleTheme"><Icon :name="dark ? 'sun' : 'moon'" :size="19" /></button>
                    <Link href="/logout" method="post" as="button" class="grid size-10 place-items-center rounded-xl transition hover:bg-[#ff6b6b]/15 hover:text-[#ff6b6b]" aria-label="Sign out" title="Sign out"><Icon name="logout" :size="19" /></Link>
                </div>
            </aside>

            <!-- section panel -->
            <aside class="glass-chrome relative flex min-w-0 flex-1 flex-col border-r border-line lg:sticky lg:top-0 lg:h-dvh" aria-label="Sections">
                <div class="flex items-start gap-2 px-5 pb-3 pt-5">
                    <div class="min-w-0 flex-1">
                        <p class="eyebrow text-brand-600">Module · 0{{ modules.indexOf(shownModule) + 1 }}</p>
                        <p class="mt-1 truncate text-[17px] font-bold tracking-[-0.02em]">{{ shownModule.label }}</p>
                        <p class="truncate text-xs text-ink-3">{{ shownModule.blurb }}</p>
                    </div>
                    <button class="rounded-lg p-1.5 text-ink-3 hover:bg-ink/5 hover:text-ink lg:hidden" aria-label="Close menu" @click="drawer = false"><Icon name="x" /></button>
                </div>
                <nav class="flex-1 overflow-y-auto px-3 pb-4" :aria-label="shownModule.label">
                    <div v-for="s in panel" :key="s.section" class="mt-4 first:mt-1">
                        <p class="eyebrow px-3 pb-1.5 text-ink-3/80">{{ s.section }}</p>
                        <div class="space-y-0.5">
                            <Link v-for="i in s.items" :key="i.href" :href="i.href" :aria-current="isActive(i.href) ? 'page' : undefined"
                                :class="isActive(i.href) ? 'bg-brand-50 text-ink shadow-[inset_0_0_0_1px_color-mix(in_oklab,var(--color-brand-400)_22%,transparent)]' : 'text-ink-2 hover:bg-ink/[0.05] hover:text-ink'"
                                class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[13.5px] font-medium transition-all duration-200">
                                <span v-if="isActive(i.href)" class="absolute inset-y-2 left-0 w-[3px] rounded-r-full bg-brand-400 shadow-[0_0_10px_var(--color-brand-400)]" aria-hidden="true" />
                                <Icon :name="i.icon" :size="17" :class="isActive(i.href) ? 'text-brand-600' : 'text-ink-3 group-hover:text-brand-600'" class="shrink-0 transition-transform duration-200 group-hover:scale-110" />
                                <span class="truncate transition-transform duration-200 group-hover:translate-x-0.5">{{ i.label }}</span>
                                <span v-if="i.badge === 'inbox' && inboxUnread" class="ml-auto grid h-5 min-w-5 place-items-center rounded-full bg-brand-gradient px-1.5 font-mono text-[10px] font-bold text-on-brand shadow-[0_0_10px_-2px_var(--color-brand-400)]">{{ inboxUnread > 99 ? '99+' : inboxUnread }}</span>
                                <kbd v-else-if="hint(i.href)" class="ml-auto hidden shrink-0 rounded border border-line px-1 font-mono text-[9.5px] text-ink-3 opacity-0 transition-opacity group-hover:opacity-100 lg:inline">{{ hint(i.href) }}</kbd>
                            </Link>
                        </div>
                    </div>
                </nav>
                <Link v-if="user" href="/profile" class="group m-3 flex items-center gap-3 rounded-2xl border border-line bg-ink/[0.03] p-2.5 transition hover:border-brand-400/40 hover:bg-brand-50">
                    <Avatar :name="user.name" size="md" />
                    <div class="min-w-0 flex-1 leading-tight"><p class="truncate text-sm font-semibold">{{ user.name }}</p><p class="eyebrow mt-0.5 truncate !text-[9.5px] text-ink-3">{{ roleLabel }}</p></div>
                    <Icon name="chevron-right" :size="16" class="text-ink-3 transition group-hover:translate-x-0.5 group-hover:text-brand-600" />
                </Link>
            </aside>
        </div>

        <div class="flex min-w-0 flex-col">
            <!-- Top bar -->
            <header class="no-print glass-chrome sticky top-0 z-20 border-b border-line">
                <div class="mx-auto flex h-16 w-full max-w-[1480px] items-center gap-2 px-3 sm:gap-3 sm:px-6 lg:px-8">
                    <button class="grid size-10 place-items-center rounded-xl text-ink-2 hover:bg-ink/5 lg:hidden" aria-label="Open menu" @click="drawer = true"><Icon name="menu" /></button>
                    <nav v-if="current" class="hidden min-w-0 items-center gap-2 md:flex" aria-label="Breadcrumb">
                        <span class="eyebrow text-ink-3">{{ moduleOf(current.module)?.label }}</span><span class="text-ink-3/50">/</span>
                        <template v-if="current.section !== moduleOf(current.module)?.label"><span class="eyebrow text-ink-3">{{ current.section }}</span><span class="text-ink-3/50">/</span></template>
                        <Link :href="current.href" class="truncate text-[13px] font-semibold text-ink hover:text-brand-600">{{ current.label }}</Link>
                    </nav>
                    <button class="group ml-auto flex h-10 min-w-0 flex-1 items-center gap-2.5 rounded-full border border-line bg-ink/[0.03] px-4 text-sm text-ink-3 transition hover:border-brand-400/50 hover:bg-brand-50 hover:text-ink-2 sm:w-64 sm:flex-none lg:w-80" @click="palette = true">
                        <Icon name="search" :size="16" class="transition group-hover:text-brand-600" /><span class="truncate">Search or jump to…</span>
                        <kbd class="ml-auto hidden rounded-md border border-line bg-surface/60 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-ink-3 sm:inline">Ctrl K</kbd>
                    </button>
                    <div class="flex items-center gap-1">
                        <span class="eyebrow mr-2 hidden text-ink-3 2xl:inline">{{ greetingDate }}</span>
                        <Dropdown v-if="creates.length" width="w-60">
                            <template #trigger><button class="hidden h-10 items-center gap-1.5 rounded-full bg-brand-gradient px-4 text-sm font-semibold text-on-brand shadow-[var(--shadow-brand)] transition hover:-translate-y-0.5 hover:shadow-[0_0_0_1px_var(--color-brand-300),0_14px_34px_-8px_var(--color-brand-400)] sm:inline-flex"><Icon name="plus" :size="16" />Create</button></template>
                            <p class="eyebrow px-3 pb-1.5 pt-1.5 text-ink-3">Quick create</p>
                            <MenuItem v-for="c in creates" :key="c.href" :href="c.href" :icon="c.icon">{{ c.label }}<kbd v-if="hint(c.href)" class="ml-auto rounded border border-line px-1 font-mono text-[10px] text-ink-3">{{ hint(c.href) }}</kbd></MenuItem>
                            <button type="button" class="mt-1 flex w-full items-center gap-2.5 rounded-xl border-t border-line px-3 py-2 text-left text-xs font-semibold text-ink-3 hover:text-ink" @click="help = true"><kbd class="rounded border border-line px-1 font-mono text-[10px]">{{ keyLabel('Ctrl') }}+/</kbd>All keyboard shortcuts</button>
                        </Dropdown>
                        <button class="grid size-10 place-items-center rounded-xl text-ink-2 transition hover:bg-ink/5 hover:text-ink lg:hidden" :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'" @click="toggleTheme"><Icon :name="dark ? 'sun' : 'moon'" /></button>
                        <NotificationBell v-model:unread="unread" />
                        <Dropdown v-if="user" width="w-64">
                            <template #trigger><button class="ml-1 rounded-full ring-2 ring-transparent transition hover:ring-brand-400/50" :aria-label="`Account menu for ${user.name}`"><Avatar :name="user.name" size="md" /></button></template>
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
        <CommandPalette v-model="palette" @help="palette = false; help = true" />
        <ShortcutsHelp v-model="help" />
    </div>
</template>
