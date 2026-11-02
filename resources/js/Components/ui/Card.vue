<script setup>
import Icon from './Icon.vue';
defineProps({ title: String, subtitle: String, icon: String, padded: { type: Boolean, default: true }, flush: Boolean });
</script>

<template>
    <section class="card min-w-0 overflow-hidden">
        <header v-if="title || $slots.actions" class="flex flex-wrap items-center justify-between gap-3 px-4 pt-4 sm:px-5" :class="flush || !padded ? 'pb-4 border-b border-line' : 'pb-1'">
            <div class="flex min-w-0 items-center gap-3">
                <span v-if="icon" class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:text-brand-300"><Icon :name="icon" :size="18" /></span>
                <div class="min-w-0"><h2 class="truncate text-[15px] font-semibold">{{ title }}</h2><p v-if="subtitle" class="truncate text-xs text-ink-3">{{ subtitle }}</p></div>
            </div>
            <div class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
        </header>
        <div :class="padded && 'p-4 sm:p-5'"><slot /></div>
    </section>
</template>
