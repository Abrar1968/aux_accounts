<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TextField from '@/Components/ui/TextField.vue';
import Button from '@/Components/ui/Button.vue';

defineOptions({ layout: null });
const form = useForm({ email: '', password: '', remember: true });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Sign in" />
    <GuestLayout title="Welcome back" subtitle="Sign in to your workspace to continue.">
        <form class="space-y-5" @submit.prevent="submit">
            <TextField v-model="form.email" label="Email" type="email" autocomplete="username" :error="form.errors.email" />
            <TextField v-model="form.password" label="Password" type="password" autocomplete="current-password" :error="form.errors.password" />
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2"><input v-model="form.remember" type="checkbox" class="size-4 rounded border-line accent-[var(--color-brand-400)]" /> <span class="text-ink-2">Remember me</span></label>
                <Link href="/forgot-password" class="font-semibold text-brand-600 hover:text-brand-300">Forgot password?</Link>
            </div>
            <Button type="submit" size="lg" block :loading="form.processing" icon-right="arrow-right">Sign in</Button>
        </form>
    </GuestLayout>
</template>
