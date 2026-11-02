<script setup>
// Right-hand drawer on ≥ 640px; full-screen sheet with sticky bottom action bar on phones.
import { watch, onBeforeUnmount } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({ title: String, subtitle: String, width: { type: String, default: 'sm:max-w-lg' } });
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
            <div v-if="open" class="fixed inset-0 z-40 bg-ink/40 backdrop-blur-[2px]" @click="open = false" />
        </Transition>
        <Transition enter-from-class="translate-y-full sm:translate-y-0 sm:translate-x-full" enter-active-class="transition duration-250 ease-out" leave-to-class="translate-y-full sm:translate-y-0 sm:translate-x-full" leave-active-class="transition duration-200 ease-in">
            <aside v-if="open" role="dialog" aria-modal="true" :aria-label="title" :class="width"
                class="fixed inset-0 z-50 flex flex-col bg-surface shadow-[var(--shadow-pop)] sm:inset-y-0 sm:left-auto sm:right-0 sm:w-full sm:border-l sm:border-line">
                <header class="flex items-start gap-3 border-b border-line px-5 py-4">
                    <div class="min-w-0 flex-1"><h2 class="truncate text-base font-semibold">{{ title }}</h2><p v-if="subtitle" class="truncate text-xs text-ink-3">{{ subtitle }}</p></div>
                    <button class="rounded-md p-1.5 text-ink-3 hover:bg-surface-2 hover:text-ink" aria-label="Close" @click="open = false"><Icon name="x" /></button>
                </header>
                <div class="flex-1 overflow-y-auto px-5 py-5"><slot /></div>
                <footer v-if="$slots.footer" class="flex items-center justify-end gap-2 border-t border-line bg-surface px-5 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"><slot name="footer" /></footer>
            </aside>
        </Transition>
    </Teleport>
</template>
