<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
defineProps({ align: { type: String, default: 'right' }, width: { type: String, default: 'w-56' } });
const open = ref(false);
const root = ref(null);
const close = (e) => root.value && !root.value.contains(e.target) && (open.value = false);
onMounted(() => document.addEventListener('click', close));
onBeforeUnmount(() => document.removeEventListener('click', close));
defineExpose({ close: () => (open.value = false) });
</script>

<template>
    <div ref="root" class="relative">
        <div @click="open = !open"><slot name="trigger" :open="open" /></div>
        <Transition enter-from-class="opacity-0 -translate-y-1" enter-active-class="transition duration-150" leave-to-class="opacity-0" leave-active-class="transition duration-100">
            <div v-if="open" :class="[align === 'right' ? 'right-0' : 'left-0', width]" class="card absolute z-30 mt-2 max-w-[calc(100vw-2rem)] overflow-hidden p-1 shadow-[var(--shadow-pop)]" @click="open = false">
                <slot />
            </div>
        </Transition>
    </div>
</template>
