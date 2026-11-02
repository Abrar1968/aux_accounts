<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import MonthPicker from '@/Components/ui/MonthPicker.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Input from '@/Components/ui/Input.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { initials, money, monthLabel, pct } from '@/lib/format';

const props = defineProps({ period: String, rows: Array });
const { can } = usePermissions();
const month = computed({ get: () => props.period, set: (v) => v && router.get('/crm/leaderboard', { month: v }) });
const editing = ref(null);
const form = useForm({ owner: '', month: '', calls_target: 0, leads_target: 0, wins_target: 0 });
function edit(r) { editing.value = r; Object.assign(form, { owner: r.owner, month: props.period, calls_target: r.targets.calls, leads_target: r.targets.leads, wins_target: r.targets.wins }); }
const save = () => form.post('/crm/leaderboard/targets', { preserveScroll: true, onSuccess: () => (editing.value = null) });
const metrics = [['calls', 'Calls'], ['leads', 'New leads'], ['wins', 'Wins']];
const medal = ['bg-warning/20 text-warning', 'bg-surface-2 text-ink-2', 'bg-negative/10 text-negative'];
</script>

<template>
    <Head title="Leaderboard" />
    <PageHeader title="Caller leaderboard" :subtitle="`${monthLabel(period)} · score = average achievement of the targets set (capped at 150%)`">
        <MonthPicker v-model="month" />
    </PageHeader>
    <EmptyState v-if="!rows.length" title="No callers yet" message="Callers appear once they own leads or log calls." icon="users" />
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Card v-for="(r, i) in rows" :key="r.owner">
            <div class="flex items-center gap-3">
                <span :class="medal[i] ?? 'bg-surface-2 text-ink-3'" class="grid size-10 place-items-center rounded-full text-sm font-bold">{{ i + 1 }}</span>
                <span class="grid size-10 place-items-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">{{ initials(r.owner) }}</span>
                <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ r.owner }}</p><p class="text-xs text-ink-3">{{ r.score === null ? 'No targets set' : `Score ${pct(r.score)}` }}</p></div>
                <Button v-if="can('crm.targets.manage')" size="sm" variant="ghost" @click="edit(r)">Targets</Button>
            </div>
            <ul class="mt-4 space-y-3">
                <li v-for="[k, label] in metrics" :key="k">
                    <div class="mb-1 flex justify-between text-sm"><span class="text-ink-2">{{ label }}</span><span class="num font-medium">{{ r.actual[k] }}<span v-if="r.targets[k]" class="text-ink-3"> / {{ r.targets[k] }}</span></span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full" :class="(r.achievement[k] ?? 0) >= 1 ? 'bg-positive' : 'bg-brand-500'" :style="{ width: `${Math.min(100, (r.achievement[k] ?? 0) * 100)}%` }" /></div>
                </li>
            </ul>
            <p v-if="r.deal_value" class="mt-3 text-xs text-ink-2">Won value {{ money(r.deal_value) }}</p>
        </Card>
    </div>
    <Modal :model-value="!!editing" @update:model-value="(v) => !v && (editing = null)" :title="`Targets · ${editing?.owner} · ${monthLabel(period)}`">
        <div class="grid grid-cols-3 gap-3">
            <Input v-model="form.calls_target" type="number" min="0" label="Calls" :error="form.errors.calls_target" />
            <Input v-model="form.leads_target" type="number" min="0" label="New leads" :error="form.errors.leads_target" />
            <Input v-model="form.wins_target" type="number" min="0" label="Wins" :error="form.errors.wins_target" />
        </div>
        <template #footer><Button variant="secondary" @click="editing = null">Cancel</Button><Button :loading="form.processing" @click="save">Save targets</Button></template>
    </Modal>
</template>
