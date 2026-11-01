<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TextField from '@/Components/ui/TextField.vue';

defineOptions({ layout: null });
const form = useForm({ email: '' });
</script>

<template>
    <Head title="Reset password" />
    <GuestLayout title="Reset password" subtitle="We will email you a reset link.">
        <form class="space-y-4" @submit.prevent="form.post('/forgot-password')">
            <TextField v-model="form.email" label="Email" type="email" :error="form.errors.email" />
            <button type="submit" :disabled="form.processing" class="h-10 w-full rounded-lg bg-brand-600 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">Send link</button>
            <Link href="/login" class="block text-center text-sm text-ink-2 hover:text-ink">Back to sign in</Link>
        </form>
    </GuestLayout>
</template>
