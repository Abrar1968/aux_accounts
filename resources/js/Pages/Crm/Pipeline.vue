<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import LeadCard from '@/Components/crm/LeadCard.vue';
import Modal from '@/Components/ui/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ columns: Array, outcomes: Array });
const { can } = usePermissions();
const editable = can('crm.leads.update');
const byStage = computed(() => props.outcomes.reduce((m, o) => ((m[o.stage] ??= []).push(o.name), m), {}));
const PREFERRED = { New: 'New', Contacted: 'Call Later', Interested: 'Interested', Meeting: 'Meeting Set', Proposal: 'Demo/Proposal Sent', Negotiation: 'Negotiation', Won: 'Won', Lost: 'Lost' };
const dragging = ref(null);
const over = ref(null);
const pending = ref(null); // { lead, stage, outcome }
function request(lead, stage) {
    if (!editable || lead.stage === stage) return;
    const choices = byStage.value[stage] ?? [];
    pending.value = { lead, stage, outcome: choices.includes(PREFERRED[stage]) ? PREFERRED[stage] : choices[0], choices };
}
function confirmMove() {
    const { lead, outcome } = pending.value;
    router.put(`/crm/pipeline/${lead.id}`, { outcome }, { preserveScroll: true, onFinish: () => (pending.value = null) });
}
const onDrop = (stage) => { over.value = null; if (dragging.value) request(dragging.value, stage); dragging.value = null; };
const tones = { Won: 'border-t-positive', Lost: 'border-t-negative', Meeting: 'border-t-info', Proposal: 'border-t-info', Negotiation: 'border-t-warning' };
</script>

<template>
    <Head title="Pipeline" />
    <PageHeader title="Pipeline" subtitle="Drag a lead to another stage, or use “Move to” on the card (keyboard friendly)" />
    <div class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <section v-for="c in columns" :key="c.stage" :class="[tones[c.stage] ?? 'border-t-brand-500', over === c.stage && 'ring-2 ring-brand-500']"
            class="flex w-[82vw] shrink-0 snap-start flex-col rounded-[var(--radius-card)] border border-t-4 border-line bg-surface-2/60 sm:w-72"
            @dragover.prevent="over = c.stage" @dragleave="over = null" @drop="onDrop(c.stage)" :aria-label="`${c.stage} column`">
            <header class="flex items-center justify-between px-3 py-2.5"><h2 class="text-sm font-semibold">{{ c.stage }}</h2><span class="num rounded-full bg-surface px-2 text-xs text-ink-2">{{ c.count }}</span></header>
            <div class="flex max-h-[68vh] flex-col gap-2 overflow-y-auto px-2 pb-3">
                <LeadCard v-for="l in c.leads" :key="l.id" :lead="l" :draggable="editable" @dragstart="dragging = l" @dragend="dragging = null">
                    <label v-if="editable" class="mt-1 flex items-center gap-2 text-xs text-ink-3">Move to
                        <select class="h-7 flex-1 rounded-md border border-line bg-surface px-1 text-xs text-ink" :value="l.stage" @change="request(l, $event.target.value); $event.target.value = l.stage">
                            <option v-for="s in columns" :key="s.stage" :value="s.stage">{{ s.stage }}</option>
                        </select>
                    </label>
                </LeadCard>
                <p v-if="!c.leads.length" class="px-2 py-6 text-center text-xs text-ink-3">No leads</p>
            </div>
        </section>
    </div>
    <Modal :model-value="!!pending" @update:model-value="(v) => !v && (pending = null)" :title="`Move to ${pending?.stage}`">
        <Select v-if="pending" v-model="pending.outcome" label="Outcome" :options="pending.choices" />
        <template #footer><Button variant="secondary" @click="pending = null">Cancel</Button><Button @click="confirmMove">Move lead</Button></template>
    </Modal>
</template>
