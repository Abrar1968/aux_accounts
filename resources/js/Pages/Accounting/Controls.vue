<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import { money } from '@/lib/format';

const props = defineProps({ controls: Array });
const failing = computed(() => props.controls.filter((c) => c.status !== 'OK').length);
const fmt = (c) => (typeof c.result === 'number' && Math.abs(c.result) >= 1000 ? money(c.result) : c.result);
</script>

<template>
    <Head title="Controls" />
    <PageHeader title="Accounting controls" :subtitle="failing ? `${failing} control(s) need attention — controls report, they never auto-fix data` : 'All controls pass'" />
    <Card :padded="false">
        <ul class="divide-y divide-line">
            <li v-for="c in controls" :key="c.key" class="grid gap-2 px-4 py-3.5 sm:grid-cols-[1fr_8rem_10rem] sm:items-center sm:px-5">
                <div class="min-w-0"><p class="text-sm font-medium">{{ c.label }}</p><p class="mt-0.5 text-xs text-ink-2">{{ c.note }}</p></div>
                <p class="num text-sm sm:text-right">{{ fmt(c) }}</p>
                <div class="sm:text-right"><Badge :label="c.status" dot /></div>
            </li>
        </ul>
    </Card>
</template>
