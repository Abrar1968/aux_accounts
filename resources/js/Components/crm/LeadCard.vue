<script setup>
import { Link } from '@inertiajs/vue3';
import PriorityBadge from './PriorityBadge.vue';
import Icon from '@/Components/ui/Icon.vue';
import { shortDate } from '@/lib/format';
defineProps({ lead: { type: Object, required: true }, draggable: Boolean });
</script>

<template>
    <article class="card group flex flex-col gap-2 p-3 text-sm" :draggable="draggable">
        <div class="flex items-start justify-between gap-2">
            <Link :href="`/crm/leads/${lead.id}`" class="num min-w-0 truncate font-semibold hover:underline">{{ lead.phone }}</Link>
            <PriorityBadge :priority="lead.priority" />
        </div>
        <p class="truncate text-xs text-ink-2">{{ lead.business_type || '—' }}<template v-if="lead.service"> · {{ lead.service }}</template></p>
        <div class="flex items-center justify-between text-xs text-ink-3">
            <span class="min-w-0 truncate">{{ lead.outcome }}</span>
            <span class="flex shrink-0 items-center gap-2">
                <span v-if="lead.next_followup" class="whitespace-nowrap">Due {{ shortDate(lead.next_followup) }}</span>
                <a v-if="lead.whatsapp" :href="lead.whatsapp" target="_blank" rel="noopener" class="text-positive" aria-label="Open WhatsApp" @click.stop><Icon name="whatsapp" :size="15" /></a>
            </span>
        </div>
        <slot />
    </article>
</template>
