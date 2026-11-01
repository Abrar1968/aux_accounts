<script setup>
import Icon from './Icon.vue';
defineProps({ title: { type: String, default: 'Nothing here yet' }, message: String, icon: { type: String, default: 'inbox' } });
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
        <div class="mb-3 grid size-11 place-items-center rounded-full bg-surface-2 text-ink-3"><Icon :name="icon" :size="20" /></div>
        <p class="text-sm font-semibold">{{ title }}</p>
        <p v-if="message" class="mt-1 max-w-sm text-sm text-ink-2">{{ message }}</p>
        <div class="mt-4"><slot /></div>
    </div>
</template>
