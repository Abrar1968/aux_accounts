<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import ImagePicker from '@/Components/ui/ImagePicker.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import { useFilters } from '@/Composables/useFilters';
import { usePermissions } from '@/Composables/usePermissions';
import { money, pct, todayIso } from '@/lib/format';

const props = defineProps({ projects: Object, filters: Object });
const { filters } = useFilters({ q: props.filters.q ?? '', kind: props.filters.kind ?? '' });
const { can } = usePermissions();
const columns = [
    { key: 'name', label: 'Project', primary: true },
    { key: 'payment_status', label: 'Payment', secondary: true },
    { key: 'kind', label: 'Type', format: (v, r) => r.type_label },
    { key: 'total_value', label: 'Contract', align: 'right', format: (v) => money(v) },
    { key: 'revenue_recognized', label: 'Recognised', align: 'right', format: (v) => money(v) },
    { key: 'current_ar', label: 'A/R', align: 'right', format: (v) => money(v) },
    { key: 'collection_percent', label: 'Collected', align: 'right', format: (v) => pct(v) },
    { key: 'work_status', label: 'Work' },
];
const open = ref(false);
const form = useForm({ name: '', customer: '', kind: 'project', start_date: todayIso(), est_delivery_date: '', revenue_method: 'Point in Time', base_value: '', change_orders: 0, referrer: '', notes: '', client_signature: null });
const referrers = computed(() => [...new Set(form.referrer.split(/[,;&+/]+/).map((n) => n.trim()).filter(Boolean))]);
const referrerHint = computed(() => (referrers.value.length > 1
    ? `${referrers.value.length} referrers — the tier memo is split equally (${referrers.value.join(' · ')})`
    : 'Separate several people with commas; the referral memo is split equally between them'));
const submit = () => form.post('/accounting/projects', { forceFormData: true, preserveScroll: true, onSuccess: () => { open.value = false; form.reset(); } });
</script>

<template>
    <Head title="Projects" />
    <PageHeader title="Projects" subtitle="Domestic and international registers · revenue, collections and receivables">
        <Button v-if="can('accounting.projects.create')" icon="plus" class="hidden sm:inline-flex" @click="open = true">New project</Button>
    </PageHeader>
    <Card :padded="false">
        <FilterBar v-model:q="filters.q" placeholder="Search name, code, customer…">
            <select v-model="filters.kind" class="rounded-lg border border-line bg-surface px-3 text-sm" aria-label="Type">
                <option value="">All types</option><option value="project">Domestic</option><option value="international">International</option><option value="lead">Leads</option>
            </select>
        </FilterBar>
        <DataTable :columns="columns" :rows="projects.data" clickable @row="(r) => router.visit(`/accounting/projects/${r.id}`)">
            <template #cell-name="{ row }"><span class="block truncate font-medium">{{ row.name }}</span><span class="block truncate text-xs text-ink-3">{{ row.code }}</span></template>
            <template #cell-payment_status="{ value }"><Badge :label="value" /></template>
            <template #cell-work_status="{ value }"><Badge :label="value" /></template>
        </DataTable>
        <Pagination :page="projects" />
    </Card>
    <BottomBar v-if="can('accounting.projects.create')"><Button icon="plus" @click="open = true">New project</Button></BottomBar>

    <Drawer v-model="open" title="New project" subtitle="ID is assigned from the start month">
        <form id="project-form" class="space-y-4" @submit.prevent="submit">
            <Input v-model="form.name" label="Project name" required :error="form.errors.name" />
            <Input v-model="form.customer" label="Customer" hint="Defaults to the project name" />
            <div class="grid grid-cols-2 gap-3">
                <Select v-model="form.kind" label="Register" :options="[{ value: 'project', label: 'Domestic' }, { value: 'international', label: 'International' }, { value: 'lead', label: 'Lead (no revenue)' }]" />
                <Select v-model="form.revenue_method" label="Revenue method" :options="['Point in Time', 'Over Time', 'Lead / No Revenue']" :error="form.errors.revenue_method" />
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
            <ImagePicker v-model="form.client_signature" label="Client signature (optional)" hint="Printed on this client's invoices and statements only" :error="form.errors.client_signature" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="project-form" :loading="form.processing">Create project</Button></template>
    </Drawer>
</template>
