<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Input from '@/Components/ui/Input.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import PriorityBadge from '@/Components/crm/PriorityBadge.vue';
import CallDrawer from '@/Components/crm/CallDrawer.vue';
import LeadFormDrawer from '@/Components/crm/LeadFormDrawer.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { date, dateTime, money } from '@/lib/format';

const props = defineProps({ lead: Object, calls: Object, outcomes: Array, options: Object });
const l = computed(() => props.lead.data);
const { can } = usePermissions();
const calling = ref(false);
const editing = ref(false);
const counting = ref(false);
const converting = ref(false);
const countForm = useForm({ calls_made: 0 });
const convertForm = useForm({ name: '', base_value: '' });
const openCount = () => { countForm.calls_made = l.value.calls_made; counting.value = true; };
const openConvert = () => { convertForm.name = `${l.value.business_type ?? 'Website'} — ${l.value.phone}`; convertForm.base_value = l.value.deal_value ?? ''; converting.value = true; };
const facts = computed(() => [
    ['Lead ID', l.value.code], ['Lead date', `${date(l.value.lead_date)} (${l.value.day})`], ['Days open', l.value.days_open], ['Owner', l.value.owner],
    ['Business type', l.value.business_type], ['Industry', l.value.industry], ['Service', l.value.service], ['Source', l.value.source],
    ['Normalised phone', l.value.normalized_phone ?? '—'], ['Quoted price', money(l.value.quoted_price)], ['Meeting', dateTime(l.value.meeting_at)], ['Last activity', dateTime(l.value.last_activity_at)],
    ['Closed', date(l.value.closed_date)], ['Deal value', money(l.value.deal_value)], ['Lost reason', l.value.lost_reason],
]);
</script>

<template>
    <Head :title="l.phone" />
    <PageHeader :title="l.phone" :subtitle="`${l.outcome} · ${l.stage} · score ${l.score}`" back="/crm/leads">
        <PriorityBadge :priority="l.priority" />
        <Button v-if="l.whatsapp" variant="secondary" size="sm" icon="whatsapp" :href="l.whatsapp" target="_blank">WhatsApp</Button>
        <Button v-if="can('crm.leads.update')" variant="secondary" size="sm" @click="editing = true">Edit</Button>
        <Button v-if="l.stage === 'Won' && !l.project_id && can('accounting.projects.create')" variant="secondary" size="sm" @click="openConvert">Convert to project</Button>
        <Button v-if="can('crm.calls.create')" size="sm" icon="phone" class="hidden sm:inline-flex" @click="calling = true">Log call</Button>
    </PageHeader>
    <div v-if="l.data_checks?.length" class="mb-4 flex flex-wrap gap-2"><Badge v-for="c in l.data_checks" :key="c" tone="warning" :label="c" /></div>
    <div class="grid gap-4 lg:grid-cols-3">
        <Card title="Lead">
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                <template v-for="[k, v] in facts" :key="k"><dt class="text-ink-3">{{ k }}</dt><dd class="min-w-0 truncate text-right font-medium">{{ v ?? '—' }}</dd></template>
            </dl>
            <div class="mt-4 flex items-center justify-between rounded-lg bg-surface-2 px-3 py-2 text-sm">
                <span>Next follow-up <strong class="ml-1">{{ dateTime(l.next_followup) }}</strong></span>
            </div>
            <p v-if="l.notes" class="mt-4 whitespace-pre-line rounded-lg bg-surface-2 p-3 text-sm text-ink-2">{{ l.notes }}</p>
        </Card>
        <Card title="Call history" :subtitle="`${l.calls_made} calls`" class="lg:col-span-2">
            <template #actions><Button v-if="can('crm.calls.create')" size="sm" variant="ghost" @click="openCount">Set total</Button></template>
            <ol class="relative space-y-4 border-l border-line pl-5">
                <li v-for="c in calls.data" :key="c.id" class="relative">
                    <span class="absolute -left-[1.6rem] top-1 size-2.5 rounded-full ring-4 ring-surface" :class="c.count_change < 0 ? 'bg-negative' : c.entry_source === 'Call' ? 'bg-brand-500' : 'bg-ink-3'" />
                    <div class="flex flex-wrap items-center gap-2 text-sm"><span class="font-medium">{{ c.call_type }}</span><Badge :label="c.outcome" /><span class="text-xs text-ink-3">{{ dateTime(c.recorded_at) }} · {{ c.caller }}</span></div>
                    <p v-if="c.notes" class="mt-1 text-sm text-ink-2">{{ c.notes }}</p>
                </li>
                <li v-if="!calls.data.length" class="text-sm text-ink-3">No calls logged yet.</li>
            </ol>
        </Card>
    </div>
    <BottomBar v-if="can('crm.calls.create')"><Button icon="phone" @click="calling = true">Log call</Button></BottomBar>
    <CallDrawer v-model="calling" :lead="l" :outcomes="outcomes" />
    <LeadFormDrawer v-model="editing" :lead="l" :options="options" />
    <Modal v-model="counting" title="Total calls made">
        <p class="mb-3 text-sm text-ink-2">The difference is saved as a Count update / correction so history is never lost.</p>
        <Input v-model="countForm.calls_made" type="number" min="0" step="1" label="Total calls" :error="countForm.errors.calls_made" />
        <template #footer><Button variant="secondary" @click="counting = false">Cancel</Button><Button :loading="countForm.processing" @click="countForm.put(`/crm/leads/${l.id}/calls-made`, { preserveScroll: true, onSuccess: () => (counting = false) })">Save total</Button></template>
    </Modal>
    <Modal v-model="converting" title="Convert to accounting project">
        <div class="space-y-3"><Input v-model="convertForm.name" label="Project name" :error="convertForm.errors.name" /><Input v-model="convertForm.base_value" type="number" label="Contract value (৳)" /></div>
        <template #footer><Button variant="secondary" @click="converting = false">Cancel</Button><Button :loading="convertForm.processing" @click="convertForm.post(`/crm/leads/${l.id}/convert`)">Create project</Button></template>
    </Modal>
</template>
