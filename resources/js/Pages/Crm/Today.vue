<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Icon from '@/Components/ui/Icon.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import BottomBar from '@/Components/ui/BottomBar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import LeadCard from '@/Components/crm/LeadCard.vue';
import LeadFormDrawer from '@/Components/crm/LeadFormDrawer.vue';
import { date, pct } from '@/lib/format';

const props = defineProps({ summary: Object, progress: Object, due: Array });
const adding = ref(false);
</script>

<template>
    <Head title="Today" />
    <PageHeader title="Today" :subtitle="`${date(summary.today)} · action centre`">
        <Button icon="plus" class="hidden sm:inline-flex" @click="adding = true">Add lead</Button>
    </PageHeader>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="col-span-2 flex flex-col gap-3 overflow-hidden rounded-[var(--radius-card)] bg-brand-gradient p-4 text-white shadow-[var(--shadow-brand)] ring-1 ring-inset ring-white/10 sm:p-5 lg:col-span-1">
            <div class="flex items-center gap-2.5"><span class="grid size-8 place-items-center rounded-lg bg-white/18 ring-1 ring-inset ring-white/20"><Icon name="target" :size="16" /></span><p class="text-[13px] font-medium text-white/85">Daily target</p><p class="num ml-auto rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-semibold">{{ Math.round(Math.min(100, (progress.created / progress.target) * 100)) }}%</p></div>
            <p class="num text-[26px] font-bold leading-none">{{ progress.created }}<span class="text-base font-semibold text-white/70"> / {{ progress.target }}</span></p>
            <div class="h-2 overflow-hidden rounded-full bg-white/20"><div class="h-full rounded-full bg-white shadow-[0_0_12px_rgb(255_255_255/0.7)]" :style="{ width: `${Math.min(100, (progress.created / progress.target) * 100)}%` }" /></div>
            <p class="text-xs text-white/80">{{ progress.remaining }} leads to go today</p>
        </div>
        <StatCard icon="bell" label="Due / overdue" :value="String(summary.due_or_overdue)" tone="warning" hint="Call these first" />
        <StatCard icon="phone" label="Active leads" :value="String(summary.active_leads)" :hint="`${summary.total_leads} total · ${summary.lost} lost`" />
        <StatCard icon="sparkles" label="Won" :value="String(summary.won)" :hint="`Conversion ${pct(summary.conversion_rate, 1)}`" tone="positive" />
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <Card title="Call these next" subtitle="Overdue and due today, oldest first" :padded="false" class="lg:col-span-2">
            <template #actions><Link href="/crm/follow-ups" class="text-xs font-medium text-brand-600">Full queue</Link></template>
            <EmptyState v-if="!due.length" title="Inbox zero" message="No follow-ups due today." icon="check" />
            <div v-else class="grid gap-3 p-4 sm:grid-cols-2">
                <LeadCard v-for="l in due" :key="l.id" :lead="l" />
            </div>
        </Card>
        <div class="space-y-4">
            <Card title="Meetings">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-2">Today</dt><dd class="num font-semibold">{{ summary.meetings_today }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-2">Upcoming</dt><dd class="num font-semibold">{{ summary.upcoming_meetings }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-2">Office visits, time pending</dt><dd class="num font-semibold">{{ summary.time_pending_visits }}</dd></div>
                </dl>
                <Link href="/crm/meetings" class="mt-3 inline-block text-xs font-medium text-brand-600">Open meetings →</Link>
            </Card>
            <Card title="Quick links" icon="bolt">
                <div class="grid grid-cols-2 gap-2">
                    <Button variant="secondary" size="sm" icon="phone" href="/crm/leads">Leads</Button>
                    <Button variant="secondary" size="sm" icon="columns" href="/crm/pipeline">Pipeline</Button>
                    <Button variant="secondary" size="sm" icon="chart" href="/crm/reports">Reports</Button>
                    <Button variant="secondary" size="sm" icon="trend" href="/crm/analytics">Analytics</Button>
                </div>
            </Card>
        </div>
    </div>
    <BottomBar><Button icon="plus" @click="adding = true">Add lead</Button></BottomBar>
    <LeadFormDrawer v-model="adding" />
</template>
