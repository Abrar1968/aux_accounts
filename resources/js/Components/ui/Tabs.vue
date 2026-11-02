<script setup>
defineProps({ tabs: { type: Array, required: true } }); // [{key,label,count}]
const model = defineModel();
</script>

<template>
    <div class="no-print -mx-1 flex gap-1 overflow-x-auto px-1 pb-1" role="tablist">
        <button v-for="t in tabs" :key="t.key" role="tab" :aria-selected="model === t.key" @click="model = t.key"
            :class="model === t.key ? 'bg-surface text-ink shadow-[var(--shadow-card)] ring-1 ring-line' : 'text-ink-2 hover:text-ink'"
            class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition">
            {{ t.label }}<span v-if="t.count !== undefined" class="num rounded-full bg-surface-2 px-1.5 text-xs text-ink-3">{{ t.count }}</span>
        </button>
    </div>
</template>
