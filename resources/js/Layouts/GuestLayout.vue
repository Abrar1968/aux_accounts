<script setup>
// Split-screen auth layout: brand story on the left (≥ lg), form card on the right.
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ToastStack from '@/Components/ui/ToastStack.vue';
import Icon from '@/Components/ui/Icon.vue';

defineProps({ title: String, subtitle: String });
const brand = computed(() => usePage().props.guestBrand ?? {});
const features = [
    ['chart', 'Accrual statements that always balance'],
    ['phone', 'Lead CRM with follow-ups and pipeline'],
    ['receipt', 'Invoices, payslips & statements as PDF'],
    ['bell', 'Real-time alerts across your team'],
];
</script>

<template>
    <div class="relative grid min-h-dvh lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
        <!-- Brand panel -->
        <section class="relative hidden overflow-hidden bg-gradient-to-br from-side-2 via-side to-side p-12 text-white lg:flex lg:flex-col">
            <div class="pointer-events-none absolute -left-32 -top-32 size-[28rem] rounded-full bg-brand-500/35 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -bottom-40 right-0 size-[30rem] rounded-full bg-brand-alt/25 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute inset-0 opacity-[0.07] [background-image:linear-gradient(rgb(255_255_255/.6)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/.6)_1px,transparent_1px)] [background-size:44px_44px] [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)]" aria-hidden="true" />

            <div class="relative flex items-center gap-3">
                <span v-if="brand.logo" class="grid size-11 place-items-center overflow-hidden rounded-xl bg-white p-1.5 shadow-lg"><img :src="brand.logo" alt="" class="max-h-full max-w-full object-contain" /></span>
                <span v-else class="grid size-11 place-items-center rounded-xl bg-brand-gradient text-lg font-extrabold shadow-lg ring-1 ring-inset ring-white/20">{{ (brand.company || 'A').charAt(0) }}</span>
                <div><p class="text-lg font-bold tracking-tight">{{ brand.company || 'Auxtech' }}</p><p class="text-xs text-slate-400">Accounting · CRM</p></div>
            </div>

            <div class="relative my-auto max-w-lg py-12">
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-slate-300"><Icon name="sparkles" :size="14" class="text-brand-300" />Your finance & sales command centre</p>
                <h2 class="text-[40px] font-bold leading-[1.1] tracking-[-0.03em]">Run the whole business <span class="bg-gradient-to-r from-brand-300 to-brand-alt bg-clip-text text-transparent">from one place.</span></h2>
                <p class="mt-4 text-[15px] leading-relaxed text-slate-400">Accounting, payroll, receivables and your lead pipeline — live, accurate and beautifully simple.</p>
                <ul class="mt-8 grid gap-3">
                    <li v-for="[icon, text] in features" :key="text" class="flex items-center gap-3 text-sm text-slate-300">
                        <span class="grid size-9 place-items-center rounded-xl border border-white/10 bg-white/5 text-brand-300"><Icon :name="icon" :size="17" /></span>{{ text }}
                    </li>
                </ul>
            </div>

            <!-- floating preview cards -->
            <div class="relative grid grid-cols-2 gap-4">
                <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur">
                    <p class="text-xs text-slate-400">Balance check</p>
                    <p class="mt-1 text-xl font-bold">৳0.00</p>
                    <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-400/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300"><span class="size-1.5 rounded-full bg-current" />Balanced every month</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur">
                    <p class="text-xs text-slate-400">Pipeline this week</p>
                    <div class="mt-3 flex h-10 items-end gap-1.5">
                        <span v-for="(h, i) in [40, 65, 45, 80, 60, 95, 75]" :key="i" :style="{ height: `${h}%` }" class="flex-1 rounded-t bg-gradient-to-t from-brand-500/60 to-brand-300" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Form -->
        <section class="relative flex min-h-dvh items-center justify-center px-5 py-12 sm:px-8">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-brand-50 to-transparent opacity-70 lg:hidden" aria-hidden="true" />
            <div class="relative w-full max-w-[400px] animate-in">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span v-if="brand.logo" class="grid size-11 place-items-center overflow-hidden rounded-xl border border-line bg-white p-1.5 shadow-sm"><img :src="brand.logo" alt="" class="max-h-full max-w-full object-contain" /></span>
                    <span v-else class="grid size-11 place-items-center rounded-xl bg-brand-gradient text-lg font-extrabold text-white shadow-[var(--shadow-brand)]">{{ (brand.company || 'A').charAt(0) }}</span>
                    <div><p class="font-bold">{{ brand.company || 'Auxtech' }}</p><p class="text-xs text-ink-3">Accounting · CRM</p></div>
                </div>
                <h1 class="text-[28px] font-bold tracking-[-0.025em]">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1.5 text-[15px] text-ink-2">{{ subtitle }}</p>
                <div class="mt-8"><slot /></div>
                <p class="mt-10 text-center text-xs text-ink-3">© {{ new Date().getFullYear() }} {{ brand.company || 'Auxtech' }} · Secured connection</p>
            </div>
        </section>
        <ToastStack />
    </div>
</template>
