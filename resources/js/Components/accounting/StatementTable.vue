<script setup>
// Income statement / cash reconciliation / balance sheet from FinancialStatementService lines.
import { computed } from 'vue';
import { money } from '@/lib/format';

const props = defineProps({ lines: { type: Array, required: true }, sections: { type: Array, required: true } });
const TOTALS = ['total_revenue', 'total_expenses', 'net_income', 'total_cash_in', 'total_cash_out', 'net_cash_movement', 'ending_cash',
    'total_assets', 'total_liabilities', 'total_equity', 'total_liabilities_equity', 'balance_check'];
const HEAD = { income: 'Revenue', expense: 'Expenses', result: '', cash: 'Cash reconciliation', asset: 'Assets', liability: 'Liabilities', equity: 'Equity', check: '' };
const rows = computed(() => props.lines.filter((l) => props.sections.includes(l.section)));
const showHeading = (i) => i === 0 || rows.value[i - 1].section !== rows.value[i].section;
</script>

<template>
    <table class="w-full text-sm">
        <tbody>
            <template v-for="(l, i) in rows" :key="l.key">
                <tr v-if="showHeading(i) && HEAD[l.section]"><th colspan="2" class="pb-1.5 pt-5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-3 first:pt-0">{{ HEAD[l.section] }}</th></tr>
                <tr :class="TOTALS.includes(l.key) ? 'border-t border-line font-semibold' : ''" class="break-inside-avoid">
                    <td :class="TOTALS.includes(l.key) ? 'py-2' : 'py-1.5 pl-3 text-ink-2'">{{ l.label }}</td>
                    <td :class="[l.amount < 0 && 'text-negative', l.key === 'balance_check' && (l.amount === 0 ? 'text-positive' : 'text-negative')]" class="num whitespace-nowrap py-1.5 text-right">{{ money(l.amount, { decimals: true }) }}</td>
                </tr>
            </template>
        </tbody>
    </table>
</template>
