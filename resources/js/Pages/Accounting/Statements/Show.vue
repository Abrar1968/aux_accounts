<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import MonthPicker from '@/Components/ui/MonthPicker.vue';
import Button from '@/Components/ui/Button.vue';
import PrintLayout from '@/Components/print/PrintLayout.vue';
import StatementTable from '@/Components/accounting/StatementTable.vue';
import { exportStatementPdf } from '@/lib/exports';
import { printPage } from '@/lib/pdf';
import { money } from '@/lib/format';

const props = defineProps({ month: String, label: String, lines: Array });
const month = computed({ get: () => props.month, set: (v) => v && router.get(`/accounting/statements/${v}`) });
const val = (k) => props.lines.find((l) => l.key === k)?.amount ?? 0;
</script>

<template>
    <Head :title="`Statement ${label}`" />
    <PageHeader title="Financial statement pack" :subtitle="`${label} · income statement, cash reconciliation and balance sheet`">
        <MonthPicker v-model="month" />
        <Button variant="secondary" size="sm" href="/accounting/statements/grid" icon="columns">All months</Button>
        <Button variant="secondary" size="sm" icon="download" @click="exportStatementPdf(label, lines)">PDF</Button>
        <Button size="sm" icon="printer" @click="printPage(`Auxtech statement ${label}`)">Print</Button>
    </PageHeader>
    <PrintLayout :title="`Financial statements — ${label}`" subtitle="Accrual basis" :meta="[{ label: 'Net income', value: money(val('net_income')) }, { label: 'Ending cash', value: money(val('ending_cash')) }, { label: 'Balance check', value: val('balance_check') === 0 ? 'Balanced' : money(val('balance_check')) }]">
        <div class="grid items-start gap-10 lg:grid-cols-2 print:grid-cols-2">
            <section><h2 class="mb-3 text-sm font-semibold">Income statement</h2><StatementTable :lines="lines" :sections="['income', 'expense', 'result']" /></section>
            <section><h2 class="mb-3 text-sm font-semibold">Cash reconciliation</h2><StatementTable :lines="lines" :sections="['cash']" /></section>
            <section class="lg:col-span-2 print:col-span-2"><h2 class="mb-3 text-sm font-semibold">Balance sheet</h2>
                <div class="grid items-start gap-10 lg:grid-cols-2 print:grid-cols-2">
                    <StatementTable :lines="lines" :sections="['asset']" />
                    <StatementTable :lines="lines" :sections="['liability', 'equity', 'check']" />
                </div>
            </section>
        </div>
    </PrintLayout>
</template>
