<script setup>
defineProps({ title: String, subtitle: String, padded: { type: Boolean, default: true } });
</script>

<template>
    <section class="card min-w-0">
        <header v-if="title || $slots.actions" class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 sm:px-5">
            <div class="min-w-0"><h2 class="truncate text-sm font-semibold">{{ title }}</h2><p v-if="subtitle" class="truncate text-xs text-ink-3">{{ subtitle }}</p></div>
            <div class="flex items-center gap-2"><slot name="actions" /></div>
        </header>
        <div :class="padded && 'p-4 sm:p-5'"><slot /></div>
    </section>
</template>
