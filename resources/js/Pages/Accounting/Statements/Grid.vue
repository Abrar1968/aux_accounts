<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import { money, monthLabel } from '@/lib/format';
import { printPage } from '@/lib/pdf';

const props = defineProps({ lines: Object, grid: Object });
const months = computed(() => Object.keys(props.grid));
const keys = computed(() => Object.keys(props.lines));
const isTotal = (k) => /^(total_|net_|ending_cash|balance_check)/.test(k);
const sectionStart = (i) => i === 0 || props.lines[keys.value[i]][1] !== props.lines[keys.value[i - 1]][1];
</script>

<template>
    <Head title="Monthly statements" />
    <PageHeader title="Monthly statements" subtitle="Every month side by side (workbook Monthly_Statements)">
        <Button size="sm" variant="secondary" icon="printer" @click="printPage('Auxtech monthly statements')">Print</Button>
    </PageHeader>
    <div class="card overflow-hidden">
        <div class="max-h-[75vh] overflow-auto">
            <table class="w-full min-w-[56rem] text-sm">
                <thead class="sticky top-0 z-10 bg-surface-2 text-[11px] uppercase tracking-wider text-ink-3">
                    <tr><th class="sticky left-0 z-10 bg-surface-2 px-4 py-2.5 text-left">Line</th><th v-for="m in months" :key="m" class="px-3 py-2.5 text-right">{{ monthLabel(m) }}</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(k, i) in keys" :key="k" :class="[isTotal(k) && 'font-semibold', sectionStart(i) && 'border-t-2 border-line']" class="border-t border-line/60">
                        <td class="sticky left-0 bg-surface px-4 py-1.5 whitespace-nowrap" :class="!isTotal(k) && 'text-ink-2'">{{ lines[k][0] }}</td>
                        <td v-for="m in months" :key="m" :class="grid[m][k] < 0 && 'text-negative'" class="num whitespace-nowrap px-3 py-1.5 text-right">{{ money(grid[m][k]) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
