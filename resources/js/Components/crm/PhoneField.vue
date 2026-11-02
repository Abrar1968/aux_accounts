<script setup>
// Live phone normalisation + duplicate warning (/ajax/leads/phone-check).
import { ref, watch } from 'vue';
import Input from '@/Components/ui/Input.vue';
import { getJson } from '@/Composables/useJson';

defineProps({ error: String });
const model = defineModel();
const state = ref(null);
let t;
watch(model, (v) => {
    clearTimeout(t);
    if (!v || String(v).length < 6) { state.value = null; return; }
    t = setTimeout(async () => { try { state.value = await getJson('/ajax/leads/phone-check', { phone: v }); } catch { state.value = null; } }, 300);
});
</script>

<template>
    <div>
        <Input v-model="model" type="tel" inputmode="tel" label="Phone" required placeholder="01XXXXXXXXX or +country…" :error="error" />
        <p v-if="state && !state.valid" class="mt-1 text-xs text-warning">Not a valid Bangladesh or +international number.</p>
        <p v-else-if="state?.duplicates?.length" class="mt-1 text-xs text-negative">Duplicate of {{ state.duplicates.map((d) => d.code).join(', ') }} ({{ state.duplicates[0].outcome }}).</p>
        <p v-else-if="state?.valid" class="mt-1 text-xs text-positive">Normalised: {{ state.normalized }}</p>
    </div>
</template>
