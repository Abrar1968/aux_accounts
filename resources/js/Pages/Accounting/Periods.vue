<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { dateTime, monthLabel } from '@/lib/format';

defineProps({ periods: Array });
const { can } = usePermissions();
const target = ref(null);
const busy = ref(false);
const key = (m) => String(m).slice(0, 7);
function confirm(reason) {
    busy.value = true;
    const p = target.value;
    router.post(`/accounting/periods/${key(p.month)}/${p.status === 'closed' ? 'reopen' : 'close'}`, p.status === 'closed' ? { reason } : {}, { preserveScroll: true, onFinish: () => { busy.value = false; target.value = null; } });
}
</script>

<template>
    <Head title="Periods" />
    <PageHeader title="Reporting periods" subtitle="Closing a month locks every entry dated in it; reopening needs a reason and is audited" />
    <Card :padded="false">
        <ul class="divide-y divide-line">
            <li v-for="p in [...periods].reverse()" :key="p.id" class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
                <div class="min-w-0 flex-1"><p class="font-medium">{{ monthLabel(p.month) }}</p><p class="truncate text-xs text-ink-3">{{ p.closed_at ? `Closed ${dateTime(p.closed_at)}` : p.notes ?? '' }}</p></div>
                <Badge :label="p.status" dot />
                <Button v-if="p.status !== 'closed' && can('accounting.periods.manage')" size="sm" variant="secondary" icon="lock" @click="target = p">Close</Button>
                <Button v-else-if="p.status === 'closed' && can('accounting.periods.reopen')" size="sm" variant="ghost" @click="target = p">Reopen</Button>
            </li>
        </ul>
    </Card>
    <ConfirmDialog :model-value="!!target" @update:model-value="(v) => !v && (target = null)" :loading="busy" :reason="target?.status === 'closed'" :danger="target?.status !== 'closed'"
        :title="target?.status === 'closed' ? `Reopen ${monthLabel(target?.month)}?` : `Close ${monthLabel(target?.month)}?`"
        :message="target?.status === 'closed' ? 'Entries in this month become editable again.' : 'No one can add or change entries dated in this month until it is reopened.'"
        :confirm-label="target?.status === 'closed' ? 'Reopen' : 'Close period'" @confirm="confirm" />
</template>
