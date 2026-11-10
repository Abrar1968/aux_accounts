<script>
import { todayIso } from '@/lib/format';
import { useCrud } from '@/Composables/useCrud';

/** Project create / edit form state (shared by the Projects list and a project's page). */
export function useProjectCrud() {
    return useCrud({
        base: '/accounting/projects', forceFormData: true,
        blank: {
            name: '', customer: '', kind: 'project', start_date: todayIso(), est_delivery_date: '', revenue_method: 'Point in Time', base_value: '', change_orders: 0, discount: 0, referrer: '', notes: '', client_signature: null,
            client_email: '', client_phone: '', client_address: '', domain: '', domain_provided_by: '', hosting_provided_by: '', project_manager: '',
        },
        toForm: (r) => ({
            ...r, start_date: r.start_date?.slice(0, 10), est_delivery_date: r.est_delivery_date?.slice(0, 10) ?? '', referrer: r.referrer ?? '', notes: r.notes ?? '', customer: r.customer ?? '',
            change_orders: r.change_orders ?? 0, discount: r.discount ?? 0,
            ...Object.fromEntries(['client_email', 'client_phone', 'client_address', 'domain', 'domain_provided_by', 'hosting_provided_by', 'project_manager'].map((k) => [k, r[k] ?? ''])),
        }),
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
import { money } from '@/lib/format';

const props = defineProps({ crud: { type: Object, required: true } });
const open = props.crud.open;
const editing = props.crud.editing;
const form = props.crud.form;
const PROVIDERS = [{ value: 'client', label: 'Client' }, { value: 'auxtech', label: 'Auxtech' }, { value: 'not_needed', label: 'Not needed' }];
const contract = computed(() => Number(form.base_value || 0) + Number(form.change_orders || 0) - Number(form.discount || 0));
const referrers = computed(() => [...new Set(String(form.referrer ?? '').split(/[,;&+/]+/).map((n) => n.trim()).filter(Boolean))]);
const referrerHint = computed(() => (referrers.value.length > 1
    ? `${referrers.value.length} referrers — the tier memo is split equally (${referrers.value.join(' · ')})`
    : 'Separate several people with commas; the referral memo is split equally between them'));
</script>

<template>
    <Drawer v-model="open" :title="editing ? `Edit ${editing.code}` : 'New project'" :subtitle="editing ? editing.name : 'ID is assigned from the start month'" :icon="editing ? 'pencil' : 'briefcase'">
        <form id="project-form" class="space-y-4" @submit.prevent="crud.submit()">
            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-3">Client information</p>
            <Input v-model="form.customer" label="Client name" hint="Defaults to the project name" :error="form.errors.customer" />
            <div class="grid grid-cols-2 gap-3">
                <Input v-model="form.client_email" type="email" label="Email" :error="form.errors.client_email" />
                <Input v-model="form.client_phone" type="tel" label="Phone" :error="form.errors.client_phone" />
            </div>
            <Input v-model="form.client_address" label="Address" :error="form.errors.client_address" />

            <p class="pt-2 text-[11px] font-bold uppercase tracking-[0.12em] text-ink-3">Project information</p>
            <div class="grid grid-cols-2 gap-3">
                <Input v-model="form.name" label="Project title" required :error="form.errors.name" />
                <Input v-model="form.domain" label="Domain" placeholder="e.g. appareldrops.shop" :error="form.errors.domain" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Select v-model="form.kind" :disabled="!!editing" label="Register" :options="[{ value: 'project', label: 'Domestic' }, { value: 'international', label: 'International' }, { value: 'lead', label: 'Lead (no revenue)' }]" />
                <Select v-model="form.revenue_method" label="Revenue method" :options="['Point in Time', 'Point in Time (Provisional)', 'Point in Time + Change Order', 'Over Time', 'Lead / No Revenue']" :error="form.errors.revenue_method" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Input v-model="form.start_date" type="date" label="Assign date" required :error="form.errors.start_date" />
                <Input v-model="form.est_delivery_date" type="date" label="Delivery date" :error="form.errors.est_delivery_date" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Select v-model="form.domain_provided_by" label="Domain provided by" placeholder="Not set" :options="PROVIDERS" :error="form.errors.domain_provided_by" />
                <Select v-model="form.hosting_provided_by" label="Hosting provided by" placeholder="Not set" :options="PROVIDERS" :error="form.errors.hosting_provided_by" />
            </div>
            <Input v-model="form.project_manager" label="Project manager / handed to" placeholder="e.g. Dhaka Culture" :error="form.errors.project_manager" />

            <p class="pt-2 text-[11px] font-bold uppercase tracking-[0.12em] text-ink-3">Payment information</p>
            <div class="grid grid-cols-2 gap-3">
                <MoneyInput v-model="form.base_value" label="Project valuation" required :error="form.errors.base_value" />
                <MoneyInput v-model="form.discount" label="Discount" :error="form.errors.discount" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <MoneyInput v-model="form.change_orders" label="Change orders" />
                <div class="flex flex-col justify-end rounded-xl border border-dashed border-line bg-surface-2/60 px-3 py-2"><span class="text-[11px] font-semibold uppercase tracking-wide text-ink-3">Contract value</span><span class="num text-base font-bold">{{ money(contract, { decimals: true }) }}</span></div>
            </div>
            <Input v-model="form.referrer" label="Referrer(s)" placeholder="e.g. Rizwan, Abrar" :hint="referrerHint" :error="form.errors.referrer" />
            <Textarea v-model="form.notes" label="Notes" />
            <ImagePicker v-if="!editing" v-model="form.client_signature" label="Client signature (optional)" hint="Printed on this client's invoices and statements only" :error="form.errors.client_signature" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="project-form" :loading="form.processing">{{ editing ? 'Save changes' : 'Create project' }}</Button></template>
    </Drawer>
</template>
