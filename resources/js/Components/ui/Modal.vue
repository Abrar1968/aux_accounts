<script setup>
import Icon from './Icon.vue';
defineProps({ title: String, subtitle: String, icon: String, tone: { type: String, default: 'brand' }, maxWidth: { type: String, default: 'max-w-md' } });
const open = defineModel({ type: Boolean });
const tones = { brand: 'bg-brand-50 text-brand-600 dark:text-brand-300', danger: 'bg-negative/10 text-negative', warning: 'bg-warning/12 text-warning' };
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" enter-active-class="transition duration-200" leave-to-class="opacity-0" leave-active-class="transition duration-150">
            <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-[#05070f]/55 p-0 backdrop-blur-sm sm:items-center sm:p-4" @click.self="open = false" @keydown.esc="open = false">
                <div role="dialog" aria-modal="true" :aria-label="title" :class="maxWidth" class="card w-full animate-in rounded-b-none p-5 shadow-[var(--shadow-pop)] sm:rounded-[20px] sm:p-6">
                    <div v-if="title" class="flex items-start gap-3.5">
                        <span v-if="icon" :class="tones[tone]" class="grid size-10 shrink-0 place-items-center rounded-xl"><Icon :name="icon" :size="20" /></span>
                        <div class="min-w-0 flex-1"><h2 class="text-[17px] font-semibold">{{ title }}</h2><p v-if="subtitle" class="mt-0.5 text-sm text-ink-2">{{ subtitle }}</p></div>
                        <button class="-mr-1 -mt-1 rounded-lg p-1.5 text-ink-3 hover:bg-surface-2 hover:text-ink" aria-label="Close" @click="open = false"><Icon name="x" :size="18" /></button>
                    </div>
                    <div class="mt-4"><slot /></div>
                    <div v-if="$slots.footer" class="-mx-5 -mb-5 mt-6 flex flex-col-reverse gap-2 rounded-b-[20px] border-t border-line bg-surface-2/60 px-5 py-4 sm:-mx-6 sm:-mb-6 sm:flex-row sm:justify-end sm:px-6"><slot name="footer" /></div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
