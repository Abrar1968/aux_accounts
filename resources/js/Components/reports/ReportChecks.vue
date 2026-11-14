<script setup>
// Self-checks returned by FinancialReportService — every one must be 0.00.
import { computed } from 'vue';
import Icon from '@/Components/ui/Icon.vue';
import { money } from '@/lib/format';

const props = defineProps({ checks: { type: Object, required: true } });
const list = computed(() => Object.entries(props.checks).map(([label, diff]) => ({ label, diff: Number(diff), ok: Math.abs(Number(diff)) < 0.005 })));
const allOk = computed(() => list.value.every((c) => c.ok));
</script>

<template>
    <div :class="allOk ? 'border-positive/30 bg-positive/[0.05]' : 'border-negative/40 bg-negative/[0.06]'" class="mt-6 rounded-xl border p-3 text-xs break-inside-avoid">
        <p :class="allOk ? 'text-positive' : 'text-negative'" class="mb-1.5 flex items-center gap-1.5 font-semibold">
            <Icon :name="allOk ? 'check' : 'alert'" :size="14" />{{ allOk ? 'Reconciled — every check is 0.00' : 'Reconciliation difference found' }}
        </p>
        <ul class="grid gap-x-6 gap-y-0.5 sm:grid-cols-2">
            <li v-for="c in list" :key="c.label" class="flex justify-between gap-3 text-ink-3"><span>{{ c.label }}</span><span :class="c.ok ? 'text-positive' : 'font-bold text-negative'" class="num">{{ c.ok ? '0.00 ✓' : money(c.diff, { decimals: true }) }}</span></li>
        </ul>
    </div>
</template>
