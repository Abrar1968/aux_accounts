<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToasts } from '@/Composables/useToasts';
import Icon from './Icon.vue';

const { toasts, push, dismiss } = useToasts();
const page = usePage();

// Server flash → toast (e.g. "Payment PAY-261101-044 saved").
watch(() => page.props.flash?.toast, (t) => t && push(t), { immediate: true });

const tone = {
    success: 'text-positive', error: 'text-negative', warning: 'text-warning', info: 'text-info',
};
</script>

<template>
    <div class="no-print pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:inset-auto sm:right-4 sm:top-4 sm:bottom-auto sm:items-end" aria-live="polite">
        <TransitionGroup enter-from-class="opacity-0 translate-y-2" enter-active-class="transition duration-200" leave-to-class="opacity-0" leave-active-class="transition duration-150">
            <div v-for="t in toasts" :key="t.id" class="card pointer-events-auto flex w-full max-w-sm items-start gap-3 p-3.5 shadow-[var(--shadow-pop)]" role="status">
                <Icon :name="t.type === 'error' ? 'alert' : t.type === 'success' ? 'check' : 'info'" :class="tone[t.type]" class="mt-0.5 shrink-0" />
                <div class="min-w-0 flex-1">
                    <p v-if="t.title" class="text-sm font-semibold">{{ t.title }}</p>
                    <p class="text-sm text-ink-2">{{ t.message }}</p>
                </div>
                <button class="rounded p-1 text-ink-3 hover:bg-surface-2 hover:text-ink" aria-label="Dismiss" @click="dismiss(t.id)"><Icon name="x" :size="16" /></button>
            </div>
        </TransitionGroup>
    </div>
</template>
