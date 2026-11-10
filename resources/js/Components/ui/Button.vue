<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | soft | ghost | danger
    size: { type: String, default: 'md' }, // sm | md | lg
    href: String, method: String, icon: String, iconRight: String, loading: Boolean, disabled: Boolean,
    type: { type: String, default: 'button' }, block: Boolean, square: Boolean,
});
const variants = {
    primary: 'bg-brand-gradient text-on-brand shadow-[var(--shadow-brand)] ring-1 ring-inset ring-white/25 hover:-translate-y-px hover:shadow-[0_0_0_1px_var(--color-brand-300),0_14px_34px_-8px_var(--color-brand-400)] active:translate-y-0',
    secondary: 'border border-line-strong bg-ink/[0.03] text-ink backdrop-blur hover:-translate-y-px hover:border-brand-400/55 hover:bg-brand-50 hover:text-ink active:translate-y-0',
    soft: 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-400/20 hover:bg-brand-100 hover:ring-brand-400/40',
    ghost: 'text-ink-2 hover:bg-ink/[0.06] hover:text-ink',
    danger: 'bg-negative text-white shadow-[0_10px_26px_-10px_var(--color-negative)] hover:-translate-y-px hover:brightness-110',
    'danger-soft': 'text-negative hover:bg-negative/10',
};
const sizes = { sm: 'h-8 px-3.5 text-xs gap-1.5', md: 'h-10 px-[18px] text-sm gap-2', lg: 'h-12 px-6 text-[15px] gap-2' };
const squares = { sm: 'size-8', md: 'size-10', lg: 'size-12' };
const cls = computed(() => [
    'inline-flex items-center justify-center rounded-full font-semibold tracking-[-0.005em] transition-all duration-200 disabled:pointer-events-none disabled:opacity-55 whitespace-nowrap select-none',
    props.square ? squares[props.size] : sizes[props.size],
    props.block ? 'w-full' : '',
    variants[props.variant],
]);
const iconSize = computed(() => (props.size === 'sm' ? 14 : props.size === 'lg' ? 18 : 16));
</script>

<template>
    <Link v-if="href" :href="href" :method="method" :as="method && method !== 'get' ? 'button' : 'a'" :class="cls">
        <Icon v-if="icon" :name="icon" :size="iconSize" /><slot /><Icon v-if="iconRight" :name="iconRight" :size="iconSize" />
    </Link>
    <button v-else :type="type" :class="cls" :disabled="disabled || loading">
        <svg v-if="loading" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
        <Icon v-else-if="icon" :name="icon" :size="iconSize" /><slot /><Icon v-if="iconRight && !loading" :name="iconRight" :size="iconSize" />
    </button>
</template>
