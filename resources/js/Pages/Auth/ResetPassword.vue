<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TextField from '@/Components/ui/TextField.vue';

defineOptions({ layout: null });
const props = defineProps({ token: String, email: String });
const form = useForm({ token: props.token, email: props.email ?? '', password: '', password_confirmation: '' });
</script>

<template>
    <Head title="Choose a new password" />
    <GuestLayout title="Choose a new password">
        <form class="space-y-4" @submit.prevent="form.post('/reset-password')">
            <TextField v-model="form.email" label="Email" type="email" :error="form.errors.email" />
            <TextField v-model="form.password" label="New password" type="password" autocomplete="new-password" :error="form.errors.password" />
            <TextField v-model="form.password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
            <button type="submit" :disabled="form.processing" class="h-10 w-full rounded-lg bg-brand-600 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">Update password</button>
        </form>
    </GuestLayout>
</template>
