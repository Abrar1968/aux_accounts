<script setup>
// Right-hand drawer on ≥ 640px; full-screen sheet with sticky bottom action bar on phones.
import { watch, onBeforeUnmount } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({ title: String, subtitle: String, icon: String, width: { type: String, default: 'sm:max-w-lg' } });
const open = defineModel({ type: Boolean });
const onKey = (e) => e.key === 'Escape' && (open.value = false);
watch(open, (v) => {
    document.documentElement.style.overflow = v ? 'hidden' : '';
    v ? window.addEventListener('keydown', onKey) : window.removeEventListener('keydown', onKey);
});
onBeforeUnmount(() => { document.documentElement.style.overflow = ''; window.removeEventListener('keydown', onKey); });
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" enter-active-class="transition duration-200" leave-to-class="opacity-0" leave-active-class="transition duration-150">
            <div v-if="open" class="fixed inset-0 z-40 bg-[#05070f]/50 backdrop-blur-sm" @click="open = false" />
        </Transition>
        <Transition enter-from-class="translate-y-full sm:translate-y-0 sm:translate-x-full" enter-active-class="transition duration-300 ease-[cubic-bezier(.2,.8,.2,1)]" leave-to-class="translate-y-full sm:translate-y-0 sm:translate-x-full" leave-active-class="transition duration-200 ease-in">
            <aside v-if="open" role="dialog" aria-modal="true" :aria-label="title" :class="width"
                class="fixed inset-0 z-50 flex flex-col bg-surface shadow-[var(--shadow-pop)] sm:inset-y-3 sm:left-auto sm:right-3 sm:w-full sm:rounded-[20px] sm:border sm:border-line">
                <header class="relative flex items-start gap-3.5 overflow-hidden border-b border-line px-5 py-5 sm:px-6">
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-brand-50 to-transparent opacity-70" aria-hidden="true" />
                    <span class="relative grid size-10 shrink-0 place-items-center rounded-xl bg-brand-gradient text-white shadow-[var(--shadow-brand)]"><Icon :name="icon || 'plus'" :size="18" /></span>
                    <div class="relative min-w-0 flex-1"><h2 class="truncate text-[17px] font-semibold">{{ title }}</h2><p v-if="subtitle" class="truncate text-[13px] text-ink-2">{{ subtitle }}</p></div>
                    <button class="relative rounded-lg p-1.5 text-ink-3 hover:bg-surface-2 hover:text-ink" aria-label="Close" @click="open = false"><Icon name="x" /></button>
                </header>
                <div class="flex-1 overflow-y-auto px-5 py-6 sm:px-6"><slot /></div>
                <footer v-if="$slots.footer" class="flex items-center justify-end gap-2 border-t border-line bg-surface-2/50 px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:rounded-b-[20px] sm:px-6"><slot name="footer" /></footer>
            </aside>
        </Transition>
    </Teleport>
</template>
