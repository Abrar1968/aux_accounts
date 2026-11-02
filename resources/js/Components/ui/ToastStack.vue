<script setup>
import { watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useToasts } from '@/Composables/useToasts';
import Icon from './Icon.vue';

const { toasts, push, dismiss } = useToasts();
const page = usePage();

// Server flash → toast (e.g. "Payment PAY-261101-044 saved").
watch(() => page.props.flash?.toast, (t) => t && push(t), { immediate: true });

const chips = { success: 'bg-positive/12 text-positive', error: 'bg-negative/12 text-negative', warning: 'bg-warning/14 text-warning', info: 'bg-info/12 text-info' };
const bars = { success: 'bg-positive', error: 'bg-negative', warning: 'bg-warning', info: 'bg-info' };
</script>

<template>
    <div class="no-print pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2.5 p-4 sm:inset-auto sm:right-5 sm:top-5 sm:bottom-auto sm:items-end" aria-live="polite">
        <TransitionGroup enter-from-class="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-4" enter-active-class="transition duration-300 ease-out" leave-to-class="opacity-0 scale-95" leave-active-class="transition duration-150">
            <div v-for="t in toasts" :key="t.id" class="pointer-events-auto relative flex w-full max-w-sm items-start gap-3 overflow-hidden rounded-2xl border border-line bg-surface/95 p-3.5 pr-3 shadow-[var(--shadow-pop)] backdrop-blur" role="status">
                <span :class="bars[t.type] ?? bars.info" class="absolute inset-y-0 left-0 w-1" aria-hidden="true" />
                <span :class="chips[t.type] ?? chips.info" class="ml-1 grid size-8 shrink-0 place-items-center rounded-full"><Icon :name="t.type === 'error' ? 'alert' : t.type === 'success' ? 'check' : t.type === 'warning' ? 'alert' : 'info'" :size="16" /></span>
                <div class="min-w-0 flex-1 pt-0.5">
                    <p v-if="t.title" class="text-sm font-semibold">{{ t.title }}</p>
                    <p class="text-[13px] leading-snug text-ink-2">{{ t.message }}</p>
                    <button v-if="t.url" class="mt-1.5 text-[13px] font-semibold text-brand-600 hover:underline dark:text-brand-300" @click="dismiss(t.id); t.onOpen ? t.onOpen() : router.visit(t.url)">Open →</button>
                </div>
                <button class="rounded-lg p-1 text-ink-3 hover:bg-surface-2 hover:text-ink" aria-label="Dismiss" @click="dismiss(t.id)"><Icon name="x" :size="16" /></button>
            </div>
        </TransitionGroup>
    </div>
</template>
