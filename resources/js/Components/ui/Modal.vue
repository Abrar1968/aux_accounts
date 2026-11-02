<script setup>
defineProps({ title: String, maxWidth: { type: String, default: 'max-w-md' } });
const open = defineModel({ type: Boolean });
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" enter-active-class="transition duration-150" leave-to-class="opacity-0" leave-active-class="transition duration-100">
            <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-0 backdrop-blur-[2px] sm:items-center sm:p-4" @click.self="open = false" @keydown.esc="open = false">
                <div role="dialog" aria-modal="true" :aria-label="title" :class="maxWidth" class="card w-full rounded-b-none p-5 shadow-[var(--shadow-pop)] sm:rounded-b-[var(--radius-card)]">
                    <h2 v-if="title" class="text-base font-semibold">{{ title }}</h2>
                    <div class="mt-3"><slot /></div>
                    <div v-if="$slots.footer" class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><slot name="footer" /></div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
