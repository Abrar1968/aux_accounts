<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TextField from '@/Components/ui/TextField.vue';

defineOptions({ layout: null });
const form = useForm({ email: '', password: '', remember: true });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Sign in" />
    <GuestLayout title="Sign in" subtitle="Use your Auxtech account.">
        <form class="space-y-4" @submit.prevent="submit">
            <TextField v-model="form.email" label="Email" type="email" autocomplete="username" :error="form.errors.email" />
            <TextField v-model="form.password" label="Password" type="password" autocomplete="current-password" :error="form.errors.password" />
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2"><input v-model="form.remember" type="checkbox" class="size-4 rounded border-line" /> Remember me</label>
                <Link href="/forgot-password" class="font-medium text-brand-600 hover:underline">Forgot password?</Link>
            </div>
            <button type="submit" :disabled="form.processing" class="h-10 w-full rounded-lg bg-brand-600 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">Sign in</button>
        </form>
    </GuestLayout>
</template>
