<script setup>
// Destructive confirmation; when `reason` is true the user must type a reason (audited).
import { ref, watch } from 'vue';
import Modal from './Modal.vue';
import Button from './Button.vue';
import Textarea from './Textarea.vue';

const props = defineProps({ title: String, message: String, confirmLabel: { type: String, default: 'Confirm' }, danger: Boolean, reason: Boolean, loading: Boolean });
const open = defineModel({ type: Boolean });
const emit = defineEmits(['confirm']);
const text = ref('');
watch(open, (v) => v && (text.value = ''));
</script>

<template>
    <Modal v-model="open" :title="title">
        <p class="text-sm text-ink-2">{{ message }}</p>
        <Textarea v-if="reason" v-model="text" class="mt-3" label="Reason (saved in the audit log)" :rows="2" />
        <template #footer>
            <Button variant="secondary" @click="open = false">Cancel</Button>
            <Button :variant="danger ? 'danger' : 'primary'" :loading="loading" :disabled="reason && text.trim().length < 5" @click="emit('confirm', text)">{{ confirmLabel }}</Button>
        </template>
    </Modal>
</template>
