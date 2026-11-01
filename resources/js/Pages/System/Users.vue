<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Switch from '@/Components/ui/Switch.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import { relative } from '@/lib/format';

const props = defineProps({ users: Object, roles: Array });
const open = ref(false);
const editing = ref(null);
const form = useForm({ name: '', email: '', password: '', role_id: '', is_active: true });
function start(u = null) {
    editing.value = u; form.reset(); form.clearErrors();
    if (u) Object.assign(form, { name: u.name, email: u.email, role_id: u.role_id, is_active: u.is_active, password: '' });
    else form.role_id = props.roles.find((r) => r.name === 'caller')?.id;
    open.value = true;
}
const submit = () => (editing.value ? form.put(`/system/users/${editing.value.id}`, { onSuccess: () => (open.value = false) }) : form.post('/system/users', { onSuccess: () => (open.value = false) }));
</script>

<template>
    <Head title="Users" />
    <PageHeader title="Users" subtitle="Who can sign in and what their role allows">
        <Button variant="secondary" size="sm" href="/system/roles">Roles & permissions</Button>
        <Button icon="plus" class="hidden sm:inline-flex" @click="start()">Invite user</Button>
    </PageHeader>
    <Card :padded="false">
        <DataTable :rows="users.data" clickable @row="start" :columns="[{ key: 'name', label: 'User', primary: true }, { key: 'role', label: 'Role', secondary: true }, { key: 'email', label: 'Email' }, { key: 'is_active', label: 'Status' }, { key: 'last_login_at', label: 'Last sign-in', format: (v) => (v ? relative(v) : 'Never') }]">
            <template #cell-role="{ value }"><Badge tone="brand" :label="value" /></template>
            <template #cell-is_active="{ value }"><Badge :tone="value ? 'positive' : 'neutral'" :label="value ? 'Active' : 'Disabled'" /></template>
        </DataTable>
    </Card>
    <BottomBar><Button icon="plus" @click="start()">Invite user</Button></BottomBar>
    <Drawer v-model="open" :title="editing ? editing.name : 'Invite user'">
        <form id="user-form" class="space-y-4" @submit.prevent="submit">
            <Input v-model="form.name" label="Name" required :error="form.errors.name" />
            <Input v-model="form.email" type="email" label="Email" required :disabled="!!editing" :error="form.errors.email" />
            <Select v-model="form.role_id" label="Role" :options="roles.map((r) => ({ value: r.id, label: r.label }))" :error="form.errors.role_id" />
            <Input v-model="form.password" type="password" :label="editing ? 'New password (optional)' : 'Initial password'" autocomplete="new-password" :error="form.errors.password" />
            <Switch v-model="form.is_active" label="Active" description="Disabled users cannot sign in" />
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="user-form" :loading="form.processing">Save</Button></template>
    </Drawer>
</template>
