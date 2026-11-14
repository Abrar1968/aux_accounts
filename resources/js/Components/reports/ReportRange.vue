<script setup>
// Quick ranges (this month, quarter, financial year…) + From / To month pickers for the reports.
import { ref, watch } from 'vue';
import MonthPicker from '@/Components/ui/MonthPicker.vue';

const props = defineProps({ presets: { type: Array, default: () => [] }, from: String, to: String });
const emit = defineEmits(['change']);
const f = ref(props.from);
const t = ref(props.to);
watch(() => [props.from, props.to], ([a, b]) => { f.value = a; t.value = b; });
const apply = () => { if (f.value && t.value) emit('change', f.value <= t.value ? f.value : t.value, f.value <= t.value ? t.value : f.value); };
watch([f, t], ([a, b]) => { if (a !== props.from || b !== props.to) apply(); });
</script>

<template>
    <div class="no-print mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-1.5">
            <button v-for="p in presets" :key="p.key" type="button" @click="emit('change', p.from, p.to)"
                :class="p.from === from && p.to === to ? 'bg-brand-gradient text-on-brand shadow-[var(--shadow-brand)]' : 'border border-line bg-ink/[0.03] text-ink-2 hover:-translate-y-px hover:border-brand-400/50 hover:text-ink'"
                class="rounded-full px-3.5 py-1.5 text-[13px] font-semibold transition-all">{{ p.label }}</button>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="eyebrow text-ink-3">From</span><MonthPicker v-model="f" />
            <span class="eyebrow text-ink-3">To</span><MonthPicker v-model="t" />
        </div>
    </div>
</template>
