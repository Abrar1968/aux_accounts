<script setup>
// Split-screen auth: midnight brand story (≥ lg) beside a frosted sign-in card. Always the dark "Command" look.
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ToastStack from '@/Components/ui/ToastStack.vue';
import Icon from '@/Components/ui/Icon.vue';

defineProps({ title: String, subtitle: String });
const brand = computed(() => usePage().props.guestBrand ?? {});
const modulesList = ['Revenue', 'Spending', 'Balance sheet', 'Lead CRM'];
const had = document.documentElement.classList.contains('dark');
onMounted(() => document.documentElement.classList.add('dark'));
onBeforeUnmount(() => { if (!had) document.documentElement.classList.remove('dark'); });
</script>

<template>
    <div class="relative isolate grid min-h-dvh overflow-hidden bg-[#0a1128] text-ink lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
        <!-- ambient light -->
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute -bottom-40 -left-40 size-[42rem] rounded-full bg-[#ffb454]/[0.16] blur-[120px] [animation:drift_24s_ease-in-out_infinite]" />
            <div class="absolute -right-32 -top-40 size-[40rem] rounded-full bg-[#5b9dff]/[0.16] blur-[120px] [animation:drift_30s_ease-in-out_infinite_reverse]" />
            <div class="absolute inset-0 [background-image:linear-gradient(rgb(232_236_247/.035)_1px,transparent_1px),linear-gradient(90deg,rgb(232_236_247/.035)_1px,transparent_1px)] [background-size:56px_56px] [mask-image:radial-gradient(ellipse_at_30%_50%,black,transparent_75%)]" />
        </div>

        <!-- Brand story -->
        <section class="relative hidden flex-col justify-between p-12 xl:p-16 lg:flex">
            <div class="flex items-center gap-3">
                <span v-if="brand.mark" class="grid size-11 place-items-center overflow-hidden rounded-[14px] bg-white p-1.5 shadow-lg ring-1 ring-white/20"><img :src="brand.mark" alt="" class="max-h-full max-w-full object-contain" /></span>
                <span v-else class="grid size-11 place-items-center rounded-[14px] bg-brand-gradient text-lg font-extrabold text-on-brand">{{ (brand.app || 'A').charAt(0) }}</span>
                <p class="text-lg font-bold tracking-tight">{{ brand.app || 'Auxtech Accounts' }}</p>
            </div>

            <div class="max-w-xl py-12">
                <p class="eyebrow flex items-center gap-2.5 !text-[11.5px] text-[#ffb454]"><span class="size-1.5 rounded-full bg-[#ffb454] shadow-[0_0_10px_#ffb454]" />Accounting · CRM</p>
                <h2 class="mt-5 text-[52px] font-bold leading-[1.04] tracking-[-0.035em] xl:text-[58px]">Books, cash and leads in <span class="text-brand-gradient">one workspace.</span></h2>
                <p class="mt-5 max-w-md text-[16px] leading-relaxed text-ink-3">Accrual results, receivables, payroll and your sales pipeline — up to date every day.</p>

                <!-- floating glass previews -->
                <div class="mt-10 grid max-w-lg grid-cols-2 gap-4">
                    <div class="card lift spotlight p-4">
                        <p class="eyebrow text-ink-3">Balance check</p>
                        <p class="mt-2 font-mono text-2xl font-bold tracking-tight">৳0.00</p>
                        <p class="mt-2.5 inline-flex items-center gap-1.5 rounded-full bg-positive/12 px-2 py-0.5 text-[11px] font-semibold text-positive"><span class="size-1.5 rounded-full bg-current shadow-[0_0_6px_currentColor]" />Balanced every month</p>
                    </div>
                    <div class="card lift spotlight p-4">
                        <p class="eyebrow text-ink-3">Pipeline · week</p>
                        <div class="mt-3 flex h-12 items-end gap-1.5">
                            <span v-for="(h, i) in [38, 62, 45, 80, 58, 96, 72]" :key="i" :style="{ height: `${h}%` }" class="flex-1 rounded-t-[4px] bg-gradient-to-t from-[#ffb454]/30 to-[#ffc978] transition-all duration-500 hover:from-[#ffb454]/60" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="eyebrow flex flex-wrap gap-x-9 gap-y-2 text-ink-3"><span v-for="m in modulesList" :key="m">{{ m }}</span></div>
        </section>

        <!-- Form -->
        <section class="relative flex min-h-dvh items-center justify-center px-5 py-12 sm:px-8">
            <div class="card relative w-full max-w-[440px] animate-in p-6 sm:p-9">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span v-if="brand.mark" class="grid size-11 place-items-center overflow-hidden rounded-[14px] bg-white p-1.5"><img :src="brand.mark" alt="" class="max-h-full max-w-full object-contain" /></span>
                    <span v-else class="grid size-11 place-items-center rounded-[14px] bg-brand-gradient text-lg font-extrabold text-on-brand">{{ (brand.app || 'A').charAt(0) }}</span>
                    <div><p class="font-bold">{{ brand.app || 'Auxtech Accounts' }}</p><p class="eyebrow text-brand-600">Accounting · CRM</p></div>
                </div>
                <h1 class="text-[30px] font-bold tracking-[-0.03em]">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1.5 text-[15px] text-ink-3">{{ subtitle }}</p>
                <div class="mt-8"><slot /></div>
                <p class="mt-9 flex items-center justify-center gap-2 text-center text-xs text-ink-3"><Icon name="lock" :size="13" />© {{ new Date().getFullYear() }} {{ brand.company || 'Auxtech' }} · Secured connection</p>
            </div>
        </section>
        <ToastStack />
    </div>
</template>
