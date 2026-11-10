<script setup>
// Initials bubble with a stable colour per name.
import { computed } from 'vue';
const props = defineProps({ name: String, size: { type: String, default: 'md' }, src: String });
const PALETTE = ['from-[#ffc978] to-[#f59e2e] text-[#0a1128]', 'from-[#5b9dff] to-[#3b51c9] text-white', 'from-[#4ade9e] to-[#16946a] text-[#04241a]', 'from-[#ff8a4c] to-[#e2552c] text-white', 'from-[#a78bfa] to-[#6d4ce0] text-white', 'from-[#5eead4] to-[#0f8f86] text-[#042624]', 'from-[#ff8fa3] to-[#d9416a] text-white', 'from-[#93a4d6] to-[#3b5199] text-white'];
const initials = computed(() => String(props.name ?? '?').replace(/[^\p{L}\p{N} ]/gu, ' ').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?');
const grad = computed(() => { let h = 0; for (const c of String(props.name ?? '')) h = (h * 31 + c.charCodeAt(0)) >>> 0; return PALETTE[h % PALETTE.length]; });
const sizes = { sm: 'size-7 text-[10px]', md: 'size-9 text-xs', lg: 'size-11 text-sm' };
</script>

<template>
    <img v-if="src" :src="src" alt="" :class="sizes[size]" class="shrink-0 rounded-full object-cover ring-2 ring-surface" />
    <span v-else :class="[sizes[size], grad]" class="grid shrink-0 place-items-center rounded-full bg-gradient-to-br font-bold shadow-sm ring-2 ring-canvas/60" aria-hidden="true">{{ initials }}</span>
</template>
