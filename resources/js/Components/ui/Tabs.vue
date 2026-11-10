<script setup>
defineProps({ tabs: { type: Array, required: true } }); // [{key,label,count}]
const model = defineModel();
</script>

<template>
    <div class="no-print inline-flex max-w-full gap-1 overflow-x-auto rounded-xl border border-line bg-surface-2/70 p-1" role="tablist">
        <button v-for="t in tabs" :key="t.key" role="tab" :aria-selected="model === t.key" @click="model = t.key"
            :class="model === t.key ? 'bg-surface text-ink shadow-[var(--shadow-card)] ring-1 ring-line' : 'text-ink-2 hover:text-ink'"
            class="flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-1.5 text-[13px] font-semibold transition">
            {{ t.label }}<span v-if="t.count !== undefined" :class="model === t.key ? 'bg-brand-50 text-brand-700 dark:text-brand-300' : 'bg-surface-3 text-ink-3'" class="num rounded-full px-1.5 text-[11px]">{{ t.count }}</span>
        </button>
    </div>
</template>
