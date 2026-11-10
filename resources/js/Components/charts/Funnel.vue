<script setup>
import { computed } from 'vue';
const props = defineProps({ stages: { type: Array, default: () => [] } }); // [{stage,count}]
const max = computed(() => Math.max(1, ...props.stages.map((s) => s.count)));
</script>

<template>
    <ul class="space-y-2">
        <li v-for="s in stages" :key="s.stage" class="grid grid-cols-[6.5rem_1fr_2.5rem] items-center gap-3 text-sm">
            <span class="truncate text-ink-2">{{ s.stage }}</span>
            <span class="h-6 rounded-lg bg-ink/[0.06]"><span class="block h-full rounded-lg bg-brand-gradient shadow-[0_0_12px_-2px_var(--color-brand-400)] transition-all duration-500" :style="{ width: `${Math.max(2, (s.count / max) * 100)}%`, opacity: s.count ? 1 : 0.25 }" /></span>
            <span class="num text-right font-medium">{{ s.count }}</span>
        </li>
    </ul>
</template>
