<script setup>
// Edit / delete icon buttons for a table row (+ optional extra menu items via the default slot).
import Icon from './Icon.vue';
import Dropdown from './Dropdown.vue';
defineProps({ edit: Boolean, remove: Boolean, editLabel: { type: String, default: 'Edit' }, removeLabel: { type: String, default: 'Delete' } });
const emit = defineEmits(['edit', 'remove']);
</script>

<template>
    <div class="no-print flex items-center justify-end gap-0.5" @click.stop>
        <button v-if="edit" type="button" class="grid size-8 place-items-center rounded-lg text-ink-3 transition hover:bg-brand-50 hover:text-brand-600 dark:hover:text-brand-300" :aria-label="editLabel" :title="editLabel" @click="emit('edit')"><Icon name="pencil" :size="16" /></button>
        <button v-if="remove" type="button" class="grid size-8 place-items-center rounded-lg text-ink-3 transition hover:bg-negative/10 hover:text-negative" :aria-label="removeLabel" :title="removeLabel" @click="emit('remove')"><Icon name="trash" :size="16" /></button>
        <Dropdown v-if="$slots.default" width="w-52">
            <template #trigger><button type="button" class="grid size-8 place-items-center rounded-lg text-ink-3 transition hover:bg-surface-2 hover:text-ink" aria-label="More actions"><Icon name="more" :size="16" /></button></template>
            <slot />
        </Dropdown>
    </div>
</template>
