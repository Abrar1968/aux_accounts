<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';
defineProps({ href: String, icon: String, danger: Boolean });
defineEmits(['click']);
</script>

<template>
    <component :is="href ? Link : 'button'" :href="href" type="button" :class="danger ? 'text-negative hover:bg-negative/10' : 'text-ink-2 hover:bg-surface-2 hover:text-ink'"
        class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium" @click="$emit('click')">
        <Icon v-if="icon" :name="icon" :size="16" class="shrink-0 opacity-80" /><slot />
    </component>
</template>
