<script setup>
import Icon from './Icon.vue';
defineProps({ title: { type: String, default: 'Nothing here yet' }, message: String, icon: { type: String, default: 'inbox' } });
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div class="relative mb-4">
            <div class="absolute inset-0 scale-150 rounded-full bg-brand-500/10 blur-xl" aria-hidden="true" />
            <div class="relative grid size-14 place-items-center rounded-2xl border border-line bg-surface text-brand-500 shadow-[var(--shadow-card)]"><Icon :name="icon" :size="24" /></div>
        </div>
        <p class="text-[15px] font-semibold">{{ title }}</p>
        <p v-if="message" class="mt-1 max-w-sm text-sm text-ink-2">{{ message }}</p>
        <div class="mt-5"><slot /></div>
    </div>
</template>
