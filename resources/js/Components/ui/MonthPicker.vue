<script setup>
// Native month input (YYYY-MM) with previous / next buttons.
import Icon from './Icon.vue';
const model = defineModel();
const shift = (n) => {
    const [y, m] = (model.value ?? '').split('-').map(Number);
    const d = new Date(Date.UTC(y, m - 1 + n, 1));
    model.value = `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`;
};
</script>

<template>
    <div class="inline-flex h-9 items-center rounded-lg border border-line bg-surface">
        <button class="grid h-full w-8 place-items-center text-ink-2 hover:text-ink" aria-label="Previous month" @click="shift(-1)"><span class="rotate-90"><Icon name="chevron" :size="16" /></span></button>
        <input v-model="model" type="month" class="h-full w-[8.5rem] bg-transparent px-1 text-center text-sm outline-none" aria-label="Month" />
        <button class="grid h-full w-8 place-items-center text-ink-2 hover:text-ink" aria-label="Next month" @click="shift(1)"><span class="-rotate-90"><Icon name="chevron" :size="16" /></span></button>
    </div>
</template>
