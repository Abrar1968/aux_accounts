<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import StatCard from '@/Components/ui/StatCard.vue';
import Card from '@/Components/ui/Card.vue';
import MonthPicker from '@/Components/ui/MonthPicker.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Icon from '@/Components/ui/Icon.vue';
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
const user = computed(() => usePage().props.auth?.user);
const greeting = computed(() => { const h = Number(new Date().toLocaleString('en-GB', { hour: 'numeric', hour12: false, timeZone: 'Asia/Dhaka' })); return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'; });
const today = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Dhaka' });
const maxAr = computed(() => Math.max(1, ...props.overview.top_receivables.map((r) => Number(r.ar))));
const expenseTotal = computed(() => props.overview.expense_mix.reduce((s, e) => s + e.amount, 0));
</script>

<template>
    <Head title="Dashboard" />
    <!-- Welcome banner -->
    <section class="card spotlight relative mb-6 animate-in overflow-hidden !rounded-[26px] p-5 sm:p-7">
        <div class="pointer-events-none absolute -right-24 -top-32 size-96 rounded-full bg-brand-400/25 blur-3xl" aria-hidden="true" />
        <div class="pointer-events-none absolute -bottom-40 left-1/4 size-80 rounded-full bg-info/15 blur-3xl" aria-hidden="true" />
        <div class="pointer-events-none absolute inset-0 [background-image:linear-gradient(var(--grid-line)_1px,transparent_1px),linear-gradient(90deg,var(--grid-line)_1px,transparent_1px)] [background-size:32px_32px] [mask-image:linear-gradient(to_left,black,transparent_70%)]" aria-hidden="true" />
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="eyebrow flex items-center gap-2 text-brand-600"><span class="size-1.5 rounded-full bg-brand-400 shadow-[0_0_8px_var(--color-brand-400)]" />{{ today }}</p>
                <h1 class="mt-2 text-[26px] font-bold tracking-[-0.035em] sm:text-[36px]">{{ greeting }}, {{ user?.name?.split(' ')[0] }}</h1>
                <p class="mt-1.5 max-w-xl text-sm text-ink-3">Here’s {{ monthLabel(month) }} at a glance — accrual results, cash and the balance sheet at month end.</p>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span :class="k.balance_check === 0 ? 'bg-positive/12 text-positive ring-positive/30' : 'bg-negative/12 text-negative ring-negative/30'" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset">
                        <span class="size-1.5 rounded-full bg-current shadow-[0_0_6px_currentColor]" />{{ k.balance_check === 0 ? 'Books balanced' : `Balance check ${money(k.balance_check)}` }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-ink/[0.05] px-3 py-1 font-mono text-[11.5px] font-semibold text-ink-2 ring-1 ring-inset ring-line-strong"><Icon name="wallet" :size="13" />Cash {{ money(k.ending_cash) }}</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 lg:max-w-[32rem] lg:justify-end [&>a]:whitespace-nowrap">
                <MonthPicker v-model="month" />
                <Button variant="secondary" href="/accounting/payments?new=1" icon="inbox">Record receipt</Button>
                <Button :href="`/accounting/statements/${month}`" icon="doc">Statement pack</Button>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <StatCard variant="hero" icon="trend" label="Revenue" :value="money(k.total_revenue)" :trend="series('revenue')" :hint="`Expenses ${money(k.total_expenses)}`" />
        <StatCard icon="chart" label="Net income" :value="money(k.net_income)" :tone="k.net_income < 0 ? 'negative' : 'positive'" :trend="series('net_income')" hint="Revenue − expenses" />
        <StatCard icon="cash" label="Ending cash" :value="money(k.ending_cash)" :tone="k.ending_cash < 0 ? 'negative' : 'neutral'" :trend="series('ending_cash')" :hint="k.ending_cash < 0 ? 'Book overdraft — reconcile with bank' : 'Cash / bank'" />
        <StatCard icon="clock" label="Receivables" :value="money(k.accounts_receivable)" tone="warning" :trend="series('accounts_receivable')" :hint="`Customer advances ${money(k.customer_advances)}`" />
        <StatCard icon="box" label="Total assets" :value="money(k.total_assets)" hint="Month-end balance sheet" />
        <StatCard icon="scale" label="Total liabilities" :value="money(k.total_liabilities)" :hint="`Payables ${money(k.payables)}`" />
        <StatCard icon="pie" label="Equity" :value="money(k.total_equity)" :tone="k.total_equity < 0 ? 'negative' : 'neutral'" hint="Paid-in + retained" />
        <Link href="/accounting/controls" :class="k.balance_check === 0 ? '!border-positive/30 !bg-positive/[0.06]' : '!border-negative/30 !bg-negative/[0.06]'" class="card lift spotlight group flex flex-col justify-between gap-3 p-4 sm:p-5">
            <div class="flex items-center gap-2.5"><span :class="k.balance_check === 0 ? 'bg-positive/12 text-positive ring-positive/25' : 'bg-negative/12 text-negative ring-negative/25'" class="grid size-8 place-items-center rounded-xl ring-1 ring-inset transition-transform group-hover:scale-110"><Icon name="shield" :size="16" /></span><p class="eyebrow text-ink-3">Balance check</p></div>
            <p :class="k.balance_check === 0 ? 'text-positive' : 'text-negative'" class="text-[22px] font-bold leading-none tracking-[-0.03em] sm:text-[27px]">{{ k.balance_check === 0 ? 'Balanced' : money(k.balance_check) }}</p>
            <p class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 dark:text-brand-300">Open controls <Icon name="arrow-right" :size="13" class="transition group-hover:translate-x-0.5" /></p>
        </Link>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <Card title="Revenue vs expenses" subtitle="By month, accrual basis" icon="chart" class="xl:col-span-2">
            <BarChart :labels="labels" :series="[{ name: 'Revenue', values: series('revenue') }, { name: 'Expenses', values: series('expenses'), color: 'var(--color-negative)' }]" title="Revenue vs expenses" />
        </Card>
        <Card title="Expense mix" :subtitle="monthLabel(month)" icon="pie">
            <Donut v-if="overview.expense_mix.length" :items="overview.expense_mix" :center="money(expenseTotal, { compact: true })" center-label="total" />
            <div v-else class="flex flex-col items-center py-10 text-center"><span class="mb-3 grid size-12 place-items-center rounded-2xl bg-surface-2 text-ink-3"><Icon name="receipt" :size="22" /></span><p class="text-sm font-semibold">No expenses yet</p><p class="mt-1 text-xs text-ink-3">Bills booked in {{ monthLabel(month) }} appear here.</p></div>
        </Card>
        <Card title="Cash & receivables" subtitle="Month-end balances" icon="trend" class="xl:col-span-2">
            <LineChart :labels="labels" area :series="[{ name: 'Ending cash', values: series('ending_cash') }, { name: 'Receivables', values: series('accounts_receivable'), color: 'var(--color-info)' }]" title="Cash and receivables" />
        </Card>
        <Card title="Largest receivables" subtitle="Who owes the most" icon="clock" :padded="false">
            <template #actions><Link href="/accounting/receivables" class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-300">View all</Link></template>
            <ul class="divide-y divide-line">
                <li v-for="r in overview.top_receivables" :key="r.code" class="group px-4 py-3 transition-colors hover:bg-brand-50 sm:px-5">
                    <div class="flex items-center gap-3 text-sm">
                        <Avatar :name="r.name" size="sm" />
                        <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ r.name }}</span><span class="block truncate text-xs text-ink-3">{{ r.code }} · {{ r.kind }}</span></span>
                        <span class="num shrink-0 font-mono text-[13px] font-bold">{{ money(r.ar) }}</span>
                    </div>
                    <div class="ml-10 mt-2 h-1 overflow-hidden rounded-full bg-ink/[0.07]"><div class="h-full rounded-full bg-brand-gradient shadow-[0_0_8px_var(--color-brand-400)] transition-all duration-500" :style="{ width: `${(Number(r.ar) / maxAr) * 100}%` }" /></div>
                </li>
            </ul>
        </Card>
    </div>
</template>
