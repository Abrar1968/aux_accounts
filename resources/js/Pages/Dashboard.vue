<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Card from '@/Components/ui/Card.vue';
import MonthPicker from '@/Components/ui/MonthPicker.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import BarChart from '@/Components/charts/BarChart.vue';
import LineChart from '@/Components/charts/LineChart.vue';
import Donut from '@/Components/charts/Donut.vue';
import { money, monthLabel } from '@/lib/format';

const props = defineProps({ overview: Object, month: String });
const k = computed(() => props.overview.kpis);
const trend = computed(() => props.overview.trend);
const labels = computed(() => trend.value.map((t) => t.label.slice(0, 3)));
const series = (key) => trend.value.map((t) => t[key]);
const month = computed({ get: () => props.month, set: (v) => v && router.get('/', { month: v }, { preserveScroll: true }) });
const expenseTotal = computed(() => props.overview.expense_mix.reduce((s, e) => s + e.amount, 0));
</script>

<template>
    <Head title="Dashboard" />
    <PageHeader title="Dashboard" :subtitle="`Accrual results for ${monthLabel(month)} · cash and balance sheet at month end`">
        <MonthPicker v-model="month" />
        <Button variant="secondary" size="sm" icon="doc" :href="`/accounting/statements/${month}`">Statement pack</Button>
    </PageHeader>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Revenue" :value="money(k.total_revenue)" :trend="series('revenue')" :hint="`Expenses ${money(k.total_expenses)}`" />
        <StatCard label="Net income" :value="money(k.net_income)" :tone="k.net_income < 0 ? 'negative' : 'positive'" :trend="series('net_income')" hint="Revenue − expenses" />
        <StatCard label="Ending cash" :value="money(k.ending_cash)" :tone="k.ending_cash < 0 ? 'negative' : 'neutral'" :trend="series('ending_cash')" :hint="k.ending_cash < 0 ? 'Book overdraft — reconcile with bank' : 'Cash / bank'" />
        <StatCard label="Receivables" :value="money(k.accounts_receivable)" :trend="series('accounts_receivable')" :hint="`Customer advances ${money(k.customer_advances)}`" />
        <StatCard label="Total assets" :value="money(k.total_assets)" />
        <StatCard label="Total liabilities" :value="money(k.total_liabilities)" :hint="`Payables ${money(k.payables)}`" />
        <StatCard label="Equity" :value="money(k.total_equity)" :tone="k.total_equity < 0 ? 'negative' : 'neutral'" />
        <div class="card flex flex-col justify-between gap-2 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-ink-3">Balance check</p>
            <Badge :tone="k.balance_check === 0 ? 'positive' : 'negative'" class="self-start text-sm">{{ k.balance_check === 0 ? 'Balanced' : money(k.balance_check) }}</Badge>
            <Link href="/accounting/controls" class="text-xs font-medium text-brand-600 hover:underline">Open controls →</Link>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <Card title="Revenue vs expenses" subtitle="By month, accrual basis" class="xl:col-span-2">
            <BarChart :labels="labels" :series="[{ name: 'Revenue', values: series('revenue') }, { name: 'Expenses', values: series('expenses'), color: 'var(--color-negative)' }]" title="Revenue vs expenses" />
        </Card>
        <Card title="Expense mix" :subtitle="monthLabel(month)">
            <Donut v-if="overview.expense_mix.length" :items="overview.expense_mix" :center="money(expenseTotal, { compact: true })" center-label="total" />
            <p v-else class="py-8 text-center text-sm text-ink-3">No expenses booked this month yet.</p>
        </Card>
        <Card title="Cash & receivables" subtitle="Month-end balances" class="xl:col-span-2">
            <LineChart :labels="labels" area :series="[{ name: 'Ending cash', values: series('ending_cash') }, { name: 'Receivables', values: series('accounts_receivable'), color: 'var(--color-warning)' }]" title="Cash and receivables" />
        </Card>
        <Card title="Largest receivables" :padded="false">
            <template #actions><Link href="/accounting/receivables" class="text-xs font-medium text-brand-600">All</Link></template>
            <ul class="divide-y divide-line">
                <li v-for="r in overview.top_receivables" :key="r.code" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm sm:px-5">
                    <span class="min-w-0"><span class="block truncate font-medium">{{ r.name }}</span><span class="block truncate text-xs text-ink-3">{{ r.code }} · {{ r.kind }}</span></span>
                    <span class="num shrink-0 font-semibold">{{ money(r.ar) }}</span>
                </li>
            </ul>
        </Card>
    </div>
</template>
