<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TextField from '@/Components/ui/TextField.vue';
import Button from '@/Components/ui/Button.vue';

defineOptions({ layout: null });
const form = useForm({ email: '' });
</script>

<template>
    <Head title="Reset password" />
    <GuestLayout title="Reset password" subtitle="We will email you a reset link.">
        <form class="space-y-5" @submit.prevent="form.post('/forgot-password')">
            <TextField v-model="form.email" label="Email" type="email" :error="form.errors.email" />
            <Button type="submit" size="lg" block :loading="form.processing" icon-right="arrow-right">Send link</Button>
            <Link href="/login" class="block text-center text-sm text-ink-2 hover:text-ink">Back to sign in</Link>
        </form>
    </GuestLayout>
</template>
