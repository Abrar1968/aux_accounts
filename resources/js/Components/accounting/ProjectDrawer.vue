<script>
import { todayIso } from '@/lib/format';
import { useCrud } from '@/Composables/useCrud';

/** Project create / edit form state (shared by the Projects list and a project's page). */
export function useProjectCrud() {
    return useCrud({
        base: '/accounting/projects', forceFormData: true,
        blank: { name: '', customer: '', kind: 'project', start_date: todayIso(), est_delivery_date: '', revenue_method: 'Point in Time', base_value: '', change_orders: 0, referrer: '', notes: '', client_signature: null },
        toForm: (r) => ({ ...r, start_date: r.start_date?.slice(0, 10), est_delivery_date: r.est_delivery_date?.slice(0, 10) ?? '', referrer: r.referrer ?? '', notes: r.notes ?? '', customer: r.customer ?? '', change_orders: r.change_orders ?? 0 }),
    });
}
</script>

<script setup>
import { computed } from 'vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Button from '@/Components/ui/Button.vue';
import ImagePicker from '@/Components/ui/ImagePicker.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';

const props = defineProps({ crud: { type: Object, required: true } });
const open = props.crud.open;
const editing = props.crud.editing;
const form = props.crud.form;
const referrers = computed(() => [...new Set(String(form.referrer ?? '').split(/[,;&+/]+/).map((n) => n.trim()).filter(Boolean))]);
const referrerHint = computed(() => (referrers.value.length > 1
    ? `${referrers.value.length} referrers — the tier memo is split equally (${referrers.value.join(' · ')})`
    : 'Separate several people with commas; the referral memo is split equally between them'));
</script>

<template>
    <Drawer v-model="open" :title="editing ? `Edit ${editing.code}` : 'New project'" :subtitle="editing ? editing.name : 'ID is assigned from the start month'" :icon="editing ? 'pencil' : 'briefcase'">
        <form id="project-form" class="space-y-4" @submit.prevent="crud.submit()">
            <Input v-model="form.name" label="Project name" required :error="form.errors.name" />
            <Input v-model="form.customer" label="Customer" hint="Defaults to the project name" />
            <div class="grid grid-cols-2 gap-3">
                <Select v-model="form.kind" :disabled="!!editing" label="Register" :options="[{ value: 'project', label: 'Domestic' }, { value: 'international', label: 'International' }, { value: 'lead', label: 'Lead (no revenue)' }]" />
                <Select v-model="form.revenue_method" label="Revenue method" :options="['Point in Time', 'Point in Time (Provisional)', 'Point in Time + Change Order', 'Over Time', 'Lead / No Revenue']" :error="form.errors.revenue_method" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Input v-model="form.start_date" type="date" label="Start date" required :error="form.errors.start_date" />
                <Input v-model="form.est_delivery_date" type="date" label="Est. delivery" :error="form.errors.est_delivery_date" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <MoneyInput v-model="form.base_value" label="Base contract value" required :error="form.errors.base_value" />
                <MoneyInput v-model="form.change_orders" label="Change orders" />
            </div>
            <Input v-model="form.referrer" label="Referrer(s)" placeholder="e.g. Rizwan, Abrar" :hint="referrerHint" :error="form.errors.referrer" />
            <Textarea v-model="form.notes" label="Notes" />
            <ImagePicker v-if="!editing" v-model="form.client_signature" label="Client signature (optional)" hint="Printed on this client's invoices and statements only" :error="form.errors.client_signature" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="project-form" :loading="form.processing">{{ editing ? 'Save changes' : 'Create project' }}</Button></template>
    </Drawer>
</template>
