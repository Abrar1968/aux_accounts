<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({ user: Object });
const form = useForm({ name: props.user.name, current_password: '', password: '', password_confirmation: '' });
const save = () => form.put('/profile', { preserveScroll: true, onSuccess: () => form.reset('current_password', 'password', 'password_confirmation') });
</script>

<template>
    <Head title="Profile" />
    <PageHeader title="Profile" :subtitle="user.email" />
    <Card title="Account" class="max-w-xl">
        <form class="space-y-4" @submit.prevent="save">
            <Input v-model="form.name" label="Name" :error="form.errors.name" />
            <Input v-model="form.current_password" type="password" label="Current password" autocomplete="current-password" :error="form.errors.current_password" />
            <div class="grid gap-3 sm:grid-cols-2">
                <Input v-model="form.password" type="password" label="New password" autocomplete="new-password" :error="form.errors.password" />
                <Input v-model="form.password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" />
            </div>
            <Button type="submit" :loading="form.processing">Save profile</Button>
        </form>
    </Card>
</template>
