<script setup>
// Create or edit a lead. Lists (services, sources…) come from the shared CRM options when provided.
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Drawer from '@/Components/ui/Drawer.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import PhoneField from './PhoneField.vue';

const props = defineProps({ lead: Object, options: Object });
const open = defineModel({ type: Boolean });
const lists = computed(() => props.options?.lists ?? {});
const outcomes = computed(() => (props.options?.outcomes ?? []).map((o) => o.name));
const blank = { phone: '', business_type: '', industry: '', service: '', source: '', outcome: 'New', owner: '', quoted_price: '', meeting_at: '', meeting_details: '', deal_value: '', lost_reason: '', notes: '' };
const form = useForm({ ...blank });
watch(open, (v) => {
    if (!v) return;
    form.clearErrors();
    Object.assign(form, blank, props.lead ? Object.fromEntries(Object.keys(blank).map((k) => [k, props.lead[k] ?? ''])) : {});
    if (props.lead?.meeting_at) form.meeting_at = String(props.lead.meeting_at).slice(0, 16);
}, { immediate: true });
const clean = (d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]).filter(([k, v]) => !(props.lead === undefined && v === null)));
const submit = () => (props.lead
    ? form.transform(clean).put(`/crm/leads/${props.lead.id}`, { preserveScroll: true, onSuccess: () => (open.value = false) })
    : form.transform(clean).post('/crm/leads', { preserveScroll: true, onSuccess: () => (open.value = false) }));
const opts = (k) => lists.value[k] ?? [];
</script>

<template>
    <Drawer v-model="open" :title="lead ? 'Edit lead' : 'Add lead'" :subtitle="lead?.code">
        <form id="lead-form" class="space-y-4" @submit.prevent="submit">
            <PhoneField v-model="form.phone" :error="form.errors.phone" />
            <template v-if="options">
                <div class="grid grid-cols-2 gap-3">
                    <Select v-model="form.business_type" label="Business type" :options="opts('business_type')" placeholder="—" />
                    <Select v-model="form.industry" label="Industry" :options="opts('industry')" placeholder="—" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <Select v-model="form.service" label="Service" :options="opts('service')" placeholder="—" />
                    <Select v-model="form.source" label="Source" :options="opts('source')" placeholder="—" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <Select v-model="form.outcome" label="Outcome" :options="outcomes" :error="form.errors.outcome" />
                    <Select v-model="form.owner" label="Owner" :options="opts('owner')" placeholder="Default owner" />
                </div>
            </template>
            <MoneyInput v-model="form.quoted_price" label="Quoted price" />
            <template v-if="lead">
                <div class="grid grid-cols-2 gap-3">
                    <Input v-model="form.meeting_at" type="datetime-local" label="Meeting / visit" />
                    <Input v-model="form.meeting_details" label="Meeting details / link" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <MoneyInput v-model="form.deal_value" label="Deal value (won)" />
                    <Input v-model="form.lost_reason" label="Lost reason" />
                </div>
            </template>
            <Textarea v-model="form.notes" label="Notes" :rows="3" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="lead-form" :loading="form.processing">{{ lead ? 'Save changes' : 'Add lead' }}</Button></template>
    </Drawer>
</template>
