<script setup>
import { reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Input from '@/Components/ui/Input.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money, todayIso } from '@/lib/format';

const props = defineProps({ account: Object, from: String, to: String, ledger: Object, reconciliations: Array });
const { can } = usePermissions();
const range = reactive({ from: props.from, to: props.to });
const apply = () => router.get(window.location.pathname, range, { preserveScroll: true });
const rec = ref(false);
const form = useForm({ statement_date: todayIso(), statement_balance: '', notes: '' });
const reconcile = () => form.post(`/accounting/banking/${props.account.id}/reconcile`, { preserveScroll: true, onSuccess: () => (rec.value = false) });
</script>

<template>
    <Head :title="account?.name ?? 'Unassigned'" />
    <PageHeader :title="account?.name ?? 'Unassigned movements'" :subtitle="account ? `${account.type} · aliases: ${(account.aliases ?? []).join(', ')}` : 'Entries without an account'" back="/accounting/banking">
        <input v-model="range.from" type="date" class="h-9 rounded-lg border border-line bg-surface px-2 text-sm" aria-label="From" />
        <input v-model="range.to" type="date" class="h-9 rounded-lg border border-line bg-surface px-2 text-sm" aria-label="To" />
        <Button size="sm" variant="secondary" @click="apply">Apply</Button>
        <Button v-if="account && can('accounting.banking.reconcile')" size="sm" icon="check" @click="rec = true">Reconcile</Button>
    </PageHeader>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
        <StatCard label="Opening" :value="money(ledger.opening)" :hint="date(from)" />
        <StatCard label="Closing" :value="money(ledger.closing)" :hint="date(to)" :tone="ledger.closing < 0 ? 'negative' : 'neutral'" />
        <StatCard label="Movements" :value="String(ledger.rows.length)" class="col-span-2 lg:col-span-1" />
    </div>
    <div class="mt-4 grid gap-4" :class="account && 'xl:grid-cols-3'">
        <Card title="Ledger" :padded="false" class="xl:col-span-2">
            <DataTable dense :rows="ledger.rows" row-key="reference" empty="No movements in this range."
                :columns="[{ key: 'description', label: 'Movement', primary: true }, { key: 'amount', label: 'Amount', align: 'right', secondary: true, format: (v) => money(v) }, { key: 'date', label: 'Date', format: date }, { key: 'source', label: 'Source' }, { key: 'balance', label: 'Balance', align: 'right', format: (v) => money(v) }]">
                <template #cell-description="{ row }"><span class="block truncate font-medium">{{ row.description || row.source }}</span><span class="block truncate text-xs text-ink-3">{{ row.reference }}</span></template>
                <template #cell-amount="{ value }"><span :class="value < 0 ? 'text-negative' : 'text-positive'">{{ money(value, { sign: true }) }}</span></template>
            </DataTable>
        </Card>
        <Card v-if="account" title="Reconciliations" :padded="false">
            <ul class="divide-y divide-line">
                <li v-for="r in reconciliations" :key="r.id" class="px-4 py-3 text-sm sm:px-5">
                    <div class="flex items-center justify-between"><span class="font-medium">{{ date(r.statement_date) }}</span><Badge :tone="r.status === 'matched' ? 'positive' : 'warning'" :label="r.status === 'matched' ? 'Matched' : 'Difference'" /></div>
                    <dl class="mt-1 grid grid-cols-3 gap-2 text-xs"><div><dt class="text-ink-3">Statement</dt><dd class="num">{{ money(r.statement_balance) }}</dd></div><div><dt class="text-ink-3">Book</dt><dd class="num">{{ money(r.book_balance) }}</dd></div><div><dt class="text-ink-3">Difference</dt><dd class="num" :class="r.difference != 0 && 'text-negative'">{{ money(r.difference) }}</dd></div></dl>
                    <p v-if="r.notes" class="mt-1 text-xs text-ink-2">{{ r.notes }}</p>
                </li>
                <li v-if="!reconciliations.length" class="px-5 py-8 text-center text-sm text-ink-3">No reconciliations yet.</li>
            </ul>
        </Card>
    </div>
    <Modal v-model="rec" :title="`Reconcile ${account?.name}`">
        <p class="mb-3 text-sm text-ink-2">Enter the balance from the bank statement or wallet app. The book balance on that date is calculated automatically.</p>
        <div class="space-y-3">
            <Input v-model="form.statement_date" type="date" label="Statement date" :max="todayIso()" :error="form.errors.statement_date" />
            <Input v-model="form.statement_balance" type="number" step="0.01" prefix="৳" label="Statement balance (negative if overdrawn)" :error="form.errors.statement_balance" />
            <Input v-model="form.notes" label="Notes" />
        </div>
        <template #footer><Button variant="secondary" @click="rec = false">Cancel</Button><Button :loading="form.processing" @click="reconcile">Reconcile</Button></template>
    </Modal>
</template>
