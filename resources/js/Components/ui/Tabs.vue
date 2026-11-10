<script setup>
defineProps({ tabs: { type: Array, required: true } }); // [{key,label,count}]
const model = defineModel();
</script>

<template>
    <div class="no-print inline-flex max-w-full gap-1 overflow-x-auto rounded-full border border-line bg-ink/[0.04] p-1 backdrop-blur" role="tablist">
        <button v-for="t in tabs" :key="t.key" role="tab" :aria-selected="model === t.key" @click="model = t.key"
            :class="model === t.key ? 'bg-brand-gradient text-on-brand shadow-[var(--shadow-brand)]' : 'text-ink-2 hover:bg-ink/[0.06] hover:text-ink'"
            class="flex shrink-0 items-center gap-2 rounded-full px-4 py-1.5 text-[13px] font-semibold transition-all duration-200">
            {{ t.label }}<span v-if="t.count !== undefined" :class="model === t.key ? 'bg-navy/15 text-on-brand' : 'bg-ink/[0.07] text-ink-3'" class="rounded-full px-1.5 font-mono text-[10.5px]">{{ t.count }}</span>
        </button>
    </div>
</template>
