<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import MonthPicker from '@/Components/ui/MonthPicker.vue';
import Button from '@/Components/ui/Button.vue';
import BarChart from '@/Components/charts/BarChart.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { money, monthLabel, pct } from '@/lib/format';

const props = defineProps({ month: String, rows: Array });
const { can } = usePermissions();
const editable = can('accounting.budgets.manage');
const month = computed({ get: () => props.month, set: (v) => v && router.get('/accounting/budgets', { month: v }) });
const drafts = reactive({});
watch(() => props.rows, (rows) => rows.forEach((r) => (drafts[r.key] = r.budget ?? '')), { immediate: true });
const save = () => router.put(`/accounting/budgets/${props.month}`, { budgets: { ...drafts } }, { preserveScroll: true });
const copy = () => router.post(`/accounting/budgets/${props.month}/copy`, {}, { preserveScroll: true });
const chartRows = computed(() => props.rows.filter((r) => r.budget !== null && !r.total && !r.income));
</script>

<template>
    <Head title="Budgets" />
    <PageHeader title="Budget vs actual" :subtitle="`${monthLabel(month)} · actuals come from the accrual statement`">
        <MonthPicker v-model="month" />
        <Button v-if="editable" size="sm" variant="secondary" @click="copy">Copy last month</Button>
        <Button v-if="editable" size="sm" icon="check" @click="save">Save budgets</Button>
    </PageHeader>
    <Card v-if="chartRows.length" title="Expense lines" subtitle="Budget vs actual" class="mb-4">
        <BarChart :labels="chartRows.map((r) => r.label.split(' ')[0])" :series="[{ name: 'Budget', values: chartRows.map((r) => r.budget), color: 'var(--color-ink-3)' }, { name: 'Actual', values: chartRows.map((r) => r.actual) }]" title="Budget vs actual" />
    </Card>
    <Card :padded="false">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="bg-surface-2/70 text-[11px] uppercase tracking-wider text-ink-3"><tr><th class="px-5 py-2.5 text-left">Line</th><th class="px-3 py-2.5 text-right">Budget</th><th class="px-3 py-2.5 text-right">Actual</th><th class="px-3 py-2.5 text-right">Variance</th><th class="px-5 py-2.5 text-right">%</th></tr></thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="r in rows" :key="r.key" :class="r.total && 'bg-surface-2/40 font-semibold'">
                        <td class="px-5 py-2">{{ r.label }}</td>
                        <td class="px-3 py-1.5 text-right">
                            <input v-if="editable" v-model="drafts[r.key]" type="number" step="0.01" class="num h-8 w-32 rounded-md border border-line bg-surface px-2 text-right text-sm outline-none focus:border-brand-500" :aria-label="`Budget for ${r.label}`" />
                            <span v-else class="num">{{ money(r.budget) }}</span>
                        </td>
                        <td class="num px-3 py-2 text-right">{{ money(r.actual) }}</td>
                        <td class="num px-3 py-2 text-right" :class="r.favourable === null ? 'text-ink-3' : r.favourable ? 'text-positive' : 'text-negative'">{{ r.variance === null ? '—' : money(r.variance, { sign: true }) }}</td>
                        <td class="num px-5 py-2 text-right text-ink-2">{{ r.variance_pct === null ? '—' : pct(r.variance_pct, 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Card>
    <p class="mt-3 text-xs text-ink-3">Green = favourable (revenue above budget or cost below budget). Leave a budget blank to remove it.</p>
</template>
