<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({ roles: Array });
const drafts = reactive(Object.fromEntries(props.roles.map((r) => [r.id, r.permissions.join('\n')])));
const save = (r) => router.put(`/system/roles/${r.id}`, { permissions: drafts[r.id].split(/\s+/).filter(Boolean) }, { preserveScroll: true });
</script>

<template>
    <Head title="Roles" />
    <PageHeader title="Roles & permissions" subtitle="One permission per line · wildcards like accounting.* are supported" back="/system/users" />
    <div class="grid gap-4 md:grid-cols-2">
        <Card v-for="r in roles" :key="r.id" :title="r.label" :subtitle="r.name">
            <textarea v-model="drafts[r.id]" :disabled="r.name === 'owner'" rows="8" class="num w-full rounded-lg border border-line bg-surface p-3 font-mono text-xs outline-none focus:border-brand-500 disabled:opacity-60" :aria-label="`${r.label} permissions`" />
            <div class="mt-3 flex justify-end"><Button v-if="r.name !== 'owner'" size="sm" @click="save(r)">Save</Button><span v-else class="text-xs text-ink-3">Owner always has every permission.</span></div>
        </Card>
    </div>
</template>
