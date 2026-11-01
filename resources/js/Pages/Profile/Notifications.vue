<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Switch from '@/Components/ui/Switch.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({ preferences: Array });
const form = useForm({ preferences: props.preferences.map((p) => ({ ...p })) });
</script>

<template>
    <Head title="Notification preferences" />
    <PageHeader title="Notification preferences" subtitle="In-app = bell & centre · Push = live toast via Pusher · Email = SMTP" back="/notifications" />
    <Card :padded="false" class="max-w-3xl">
        <div class="hidden grid-cols-[1fr_5rem_5rem_5rem] gap-2 border-b border-line px-5 py-2 text-[11px] font-semibold uppercase tracking-wider text-ink-3 sm:grid"><span>Type</span><span class="text-center">In-app</span><span class="text-center">Push</span><span class="text-center">Email</span></div>
        <ul class="divide-y divide-line">
            <li v-for="p in form.preferences" :key="p.type" class="grid gap-3 px-4 py-3 sm:grid-cols-[1fr_5rem_5rem_5rem] sm:items-center sm:px-5">
                <p class="text-sm font-medium">{{ p.label }}<span v-if="p.locked" class="ml-2 text-xs font-normal text-ink-3">(always in-app)</span></p>
                <div class="flex items-center justify-between gap-2 sm:justify-center"><span class="text-xs text-ink-3 sm:hidden">In-app</span><Switch v-model="p.in_app" :disabled="p.locked" :label="undefined" /></div>
                <div class="flex items-center justify-between gap-2 sm:justify-center"><span class="text-xs text-ink-3 sm:hidden">Push</span><Switch v-model="p.push" /></div>
                <div class="flex items-center justify-between gap-2 sm:justify-center"><span class="text-xs text-ink-3 sm:hidden">Email</span><Switch v-model="p.email" /></div>
            </li>
        </ul>
        <div class="flex justify-end border-t border-line px-5 py-3"><Button :loading="form.processing" @click="form.put('/profile/notifications', { preserveScroll: true })">Save preferences</Button></div>
    </Card>
</template>
