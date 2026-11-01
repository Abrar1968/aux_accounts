<script setup>
import ToastStack from '@/Components/ui/ToastStack.vue';
defineProps({ title: String, subtitle: String });
</script>

<template>
    <div class="grid min-h-full place-items-center px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex items-center gap-2.5">
                <div class="grid size-9 place-items-center rounded-lg bg-brand-600 font-bold text-white">A</div>
                <div><p class="font-semibold">Auxtech Ops</p><p class="text-xs text-ink-3">Accounting · CRM</p></div>
            </div>
            <div class="card p-6 sm:p-7">
                <h1 class="text-lg font-semibold tracking-tight">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-ink-2">{{ subtitle }}</p>
                <div class="mt-5"><slot /></div>
            </div>
        </div>
        <ToastStack />
    </div>
</template>
