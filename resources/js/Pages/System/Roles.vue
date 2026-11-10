<script setup>
// Roles & permissions: pick a role, tick exactly what it may do (grouped, searchable). No wildcards.
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';

const props = defineProps({ roles: Array, catalog: Array });
const allKeys = props.catalog.flatMap((g) => g.items.map((i) => i.key));
const drafts = reactive(Object.fromEntries(props.roles.map((r) => [r.id, new Set(r.permissions)])));
const activeId = ref(props.roles.find((r) => r.name !== 'owner')?.id ?? props.roles[0]?.id);
const active = computed(() => props.roles.find((r) => r.id === activeId.value));
const locked = computed(() => active.value?.name === 'owner');
const set = computed(() => drafts[activeId.value]);
const q = ref('');
const saving = ref(false);

const groups = computed(() => {
    const t = q.value.trim().toLowerCase();
    return props.catalog.map((g) => ({ ...g, items: g.items.filter((i) => !t || `${g.group} ${i.label} ${i.key}`.toLowerCase().includes(t)) })).filter((g) => g.items.length);
});
const dirty = (r) => { const d = drafts[r.id]; return d.size !== r.permissions.length || r.permissions.some((k) => !d.has(k)); };
const toggle = (key) => { if (locked.value) return; const s = set.value; s.has(key) ? s.delete(key) : s.add(key); };
const groupState = (g) => { const n = g.items.filter((i) => set.value.has(i.key)).length; return n === 0 ? 'none' : n === g.items.length ? 'all' : 'some'; };
function toggleGroup(g) {
    if (locked.value) return;
    const on = groupState(g) !== 'all';
    g.items.forEach((i) => (on ? set.value.add(i.key) : set.value.delete(i.key)));
}
const selectAll = (on) => { if (!locked.value) allKeys.forEach((k) => (on ? set.value.add(k) : set.value.delete(k))); };
const reset = () => { drafts[activeId.value] = new Set(active.value.permissions); };
function save() {
    saving.value = true;
    router.put(`/system/roles/${active.value.id}`, { permissions: allKeys.filter((k) => set.value.has(k)) }, {
        preserveScroll: true,
        onSuccess: () => { drafts[active.value.id] = new Set(active.value.permissions); }, // server-saved list
        onFinish: () => (saving.value = false),
    });
}
</script>

<template>
    <Head title="Roles" />
    <PageHeader title="Roles & permissions" subtitle="Tick exactly what each role can see and do — changes apply on the user's next click" back="/system/users">
        <Button v-if="!locked" variant="secondary" :disabled="!dirty(active)" @click="reset">Undo changes</Button>
        <Button v-if="!locked" icon="check" :loading="saving" :disabled="!dirty(active)" @click="save">Save {{ active.label }}</Button>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <!-- role list -->
        <nav class="card h-fit p-2 lg:sticky lg:top-20" aria-label="Roles">
            <button v-for="r in roles" :key="r.id" type="button" :aria-current="r.id === activeId ? 'true' : undefined" @click="activeId = r.id"
                :class="r.id === activeId ? 'bg-brand-50 shadow-[inset_0_0_0_1px_color-mix(in_oklab,var(--color-brand-400)_30%,transparent)]' : 'hover:bg-ink/[0.05]'"
                class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition">
                <span :class="r.id === activeId ? 'bg-brand-gradient text-on-brand' : 'bg-ink/[0.06] text-ink-3 group-hover:text-ink'" class="grid size-9 shrink-0 place-items-center rounded-xl transition"><Icon :name="r.name === 'owner' ? 'lock' : 'shield'" :size="16" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold">{{ r.label }}</span>
                    <span class="eyebrow block truncate !text-[9.5px] text-ink-3">{{ r.name === 'owner' ? 'All' : drafts[r.id].size }} / {{ allKeys.length }} · {{ r.users_count }} user{{ r.users_count === 1 ? '' : 's' }}</span>
                </span>
                <span v-if="dirty(r)" class="size-2 rounded-full bg-brand-400 shadow-[0_0_8px_var(--color-brand-400)]" title="Unsaved changes" />
            </button>
        </nav>

        <Card :padded="false" :title="active.label" :subtitle="locked ? 'The owner always has every permission (so nobody can lock the business out).' : `${set.size} of ${allKeys.length} permissions granted`" icon="shield">
            <template #actions>
                <template v-if="!locked">
                    <Button size="sm" variant="ghost" @click="selectAll(true)">Select all</Button>
                    <Button size="sm" variant="ghost" @click="selectAll(false)">Clear all</Button>
                </template>
            </template>
            <div class="border-b border-line p-3 sm:px-5">
                <label class="relative block sm:max-w-sm">
                    <Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
                    <input v-model="q" type="search" data-page-search placeholder="Filter permissions…" aria-label="Filter permissions" class="field !h-9 !rounded-full pl-9 pr-3" @keydown.esc="q = ''" />
                </label>
            </div>
            <div class="grid gap-4 p-4 sm:p-5 xl:grid-cols-2">
                <section v-for="g in groups" :key="g.group" class="rounded-2xl border border-line bg-ink/[0.02] p-3">
                    <button type="button" class="flex w-full items-center gap-2.5 rounded-lg px-1 pb-2 text-left" :disabled="locked" @click="toggleGroup(g)">
                        <span :class="groupState(g) === 'none' ? 'border-line-strong' : 'border-brand-400 bg-brand-400 text-on-brand'" class="grid size-[18px] shrink-0 place-items-center rounded-[6px] border transition">
                            <Icon v-if="groupState(g) === 'all'" name="check" :size="12" /><span v-else-if="groupState(g) === 'some'" class="h-0.5 w-2 rounded bg-current" />
                        </span>
                        <span class="eyebrow flex-1 text-ink-2">{{ g.group }}</span>
                        <span class="font-mono text-[10.5px] text-ink-3">{{ g.items.filter((i) => set.has(i.key)).length }}/{{ g.items.length }}</span>
                    </button>
                    <ul class="space-y-0.5">
                        <li v-for="i in g.items" :key="i.key">
                            <label :class="locked ? 'cursor-not-allowed' : 'cursor-pointer hover:bg-brand-50'" class="flex items-start gap-2.5 rounded-xl px-2 py-1.5 transition">
                                <input type="checkbox" :checked="set.has(i.key)" :disabled="locked" class="mt-0.5 size-4 shrink-0 accent-[var(--color-brand-400)]" @change="toggle(i.key)" />
                                <span class="min-w-0"><span class="block text-[13px] font-medium" :class="set.has(i.key) ? 'text-ink' : 'text-ink-2'">{{ i.label }}</span><span class="block truncate font-mono text-[10.5px] text-ink-3">{{ i.key }}</span></span>
                            </label>
                        </li>
                    </ul>
                </section>
                <p v-if="!groups.length" class="py-8 text-center text-sm text-ink-3 xl:col-span-2">No permission matches “{{ q }}”.</p>
            </div>
        </Card>
    </div>
</template>
