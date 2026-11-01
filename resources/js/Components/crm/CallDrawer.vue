<script setup>
// Log a call (CallLogService) — call_uuid makes the save idempotent.
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Drawer from '@/Components/ui/Drawer.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import { newRequestId } from '@/Composables/useRequestId';

const props = defineProps({ lead: Object, outcomes: { type: Array, default: () => [] } });
const open = defineModel({ type: Boolean });
const page = usePage();
const form = useForm({ call_uuid: '', call_type: 'Follow-up', outcome: '', caller: '', notes: '', next_followup: '' });
watch(open, (v) => {
    if (!v) return;
    form.reset(); form.clearErrors();
    form.call_uuid = newRequestId();
    form.outcome = props.lead?.outcome ?? 'New';
    form.caller = props.lead?.owner ?? page.props.auth.user?.name;
    form.call_type = props.lead?.calls_made ? 'Follow-up' : 'Initial';
});
const outcomeOptions = computed(() => props.outcomes.map((o) => ({ value: o.name, label: `${o.name} · ${o.stage}` })));
const submit = () => form.post(`/crm/leads/${props.lead.id}/calls`, { preserveScroll: true, onSuccess: () => (open.value = false) });
</script>

<template>
    <Drawer v-model="open" title="Log a call" :subtitle="lead?.phone">
        <form id="call-form" class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-3">
                <Select v-model="form.call_type" label="Call type" :options="['Initial', 'Follow-up']" />
                <Input v-model="form.caller" label="Caller" />
            </div>
            <Select v-model="form.outcome" label="Outcome" :options="outcomeOptions" :error="form.errors.outcome" />
            <Input v-model="form.next_followup" type="datetime-local" label="Next follow-up (optional)" hint="Leave empty to use the outcome's default follow-up days." :error="form.errors.next_followup" />
            <Textarea v-model="form.notes" label="Call notes" :rows="4" />
        </form>
        <template #footer>
            <Button variant="secondary" @click="open = false">Cancel</Button>
            <Button type="submit" form="call-form" :loading="form.processing" icon="phone">Save call</Button>
        </template>
    </Drawer>
</template>
