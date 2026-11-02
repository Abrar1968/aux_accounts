<script setup>
import Badge from '@/Components/ui/Badge.vue';
defineProps({ priority: String });
const tones = { OVERDUE: 'negative', TODAY: 'warning', HIGH: 'brand', NORMAL: 'neutral', CLOSED: 'neutral' };
</script>

<template><Badge v-if="priority" :tone="tones[priority] ?? 'neutral'" :label="priority" dot /></template>
