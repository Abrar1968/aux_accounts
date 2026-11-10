<script setup>
// Initials bubble with a stable colour per name.
import { computed } from 'vue';
const props = defineProps({ name: String, size: { type: String, default: 'md' }, src: String });
const PALETTE = ['from-indigo-500 to-violet-500', 'from-sky-500 to-cyan-500', 'from-emerald-500 to-teal-500', 'from-amber-500 to-orange-500', 'from-rose-500 to-pink-500', 'from-fuchsia-500 to-purple-500', 'from-blue-500 to-indigo-500', 'from-lime-500 to-emerald-500'];
const initials = computed(() => String(props.name ?? '?').replace(/[^\p{L}\p{N} ]/gu, ' ').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?');
const grad = computed(() => { let h = 0; for (const c of String(props.name ?? '')) h = (h * 31 + c.charCodeAt(0)) >>> 0; return PALETTE[h % PALETTE.length]; });
const sizes = { sm: 'size-7 text-[10px]', md: 'size-9 text-xs', lg: 'size-11 text-sm' };
</script>

<template>
    <img v-if="src" :src="src" alt="" :class="sizes[size]" class="shrink-0 rounded-full object-cover ring-2 ring-surface" />
    <span v-else :class="[sizes[size], grad]" class="grid shrink-0 place-items-center rounded-full bg-gradient-to-br font-bold text-white shadow-sm ring-2 ring-surface" aria-hidden="true">{{ initials }}</span>
</template>
