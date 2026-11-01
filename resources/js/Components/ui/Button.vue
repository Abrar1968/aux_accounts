<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | ghost | danger
    size: { type: String, default: 'md' }, // sm | md
    href: String, method: String, icon: String, loading: Boolean, disabled: Boolean,
    type: { type: String, default: 'button' }, block: Boolean,
});
const variants = {
    primary: 'bg-brand-600 text-white hover:bg-brand-700 shadow-sm',
    secondary: 'border border-line bg-surface text-ink hover:bg-surface-2',
    ghost: 'text-ink-2 hover:bg-surface-2 hover:text-ink',
    danger: 'bg-negative text-white hover:opacity-90 shadow-sm',
};
const cls = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60 whitespace-nowrap',
    props.size === 'sm' ? 'h-8 px-3 text-xs' : 'h-10 px-4 text-sm',
    props.block ? 'w-full' : '',
    variants[props.variant],
]);
</script>

<template>
    <Link v-if="href" :href="href" :method="method" :as="method && method !== 'get' ? 'button' : 'a'" :class="cls">
        <Icon v-if="icon" :name="icon" :size="size === 'sm' ? 14 : 16" /><slot />
    </Link>
    <button v-else :type="type" :class="cls" :disabled="disabled || loading">
        <svg v-if="loading" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
        <Icon v-else-if="icon" :name="icon" :size="size === 'sm' ? 14 : 16" /><slot />
    </button>
</template>
