<script setup>
import Combobox from '@/Components/ui/Combobox.vue';
import { getJson } from '@/Composables/useJson';
import { money } from '@/lib/format';

defineProps({ error: String, display: String });
const model = defineModel();
const fetcher = async (q) => (await getJson('/ajax/receivables', { q })).map((r) => ({
    value: `${r.type}:${r.id}`, type: r.type, id: r.id, label: `${r.name}`,
    meta: `${r.code} · ${r.kind} · A/R ${money(r.ar)}${r.advance ? ` · advance ${money(r.advance)}` : ''}`,
}));
</script>

<template>
    <Combobox v-model="model" label="Customer / revenue record" placeholder="Search project, service or legacy due…" :fetcher="fetcher" :error="error" :display="display" />
</template>
