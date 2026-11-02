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
    <div class="inline-flex h-10 items-center rounded-xl border border-line bg-surface text-ink shadow-[var(--shadow-xs)]">
        <button class="grid h-full w-9 place-items-center rounded-l-xl text-ink-2 hover:bg-surface-2 hover:text-ink" aria-label="Previous month" @click="shift(-1)"><Icon name="arrow-left" :size="15" /></button>
        <input v-model="model" type="month" class="num h-full w-[9.5rem] bg-transparent px-1 text-center text-sm font-semibold outline-none" aria-label="Month" />
        <button class="grid h-full w-9 place-items-center rounded-r-xl text-ink-2 hover:bg-surface-2 hover:text-ink" aria-label="Next month" @click="shift(1)"><Icon name="arrow-right" :size="15" /></button>
    </div>
</template>
