<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import Icon from '@/Components/ui/Icon.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { date, money } from '@/lib/format';

const props = defineProps({ balances: Object });
const { can } = usePermissions();
const asOf = computed({ get: () => props.balances.as_of, set: (v) => v && router.get('/accounting/banking', { as_of: v }, { preserveScroll: true }) });
const open = ref(false);
const form = useForm({ name: '', type: 'bank', account_number: '', aliases: '', opening_balance: 0, opening_date: '', is_active: true, notes: '' });
const icons = { bank: 'scale', wallet: 'wallet', cash: 'box' };
</script>

<template>
    <Head title="Bank & cash" />
    <PageHeader title="Bank & cash accounts" subtitle="Book balances by account · movements are matched by the account / method recorded on each entry">
        <input v-model="asOf" type="date" class="h-9 rounded-lg border border-line bg-surface px-2 text-sm" aria-label="As of" />
        <Button v-if="can('accounting.banking.manage')" size="sm" icon="plus" @click="open = true">Add account</Button>
    </PageHeader>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Link v-for="a in balances.accounts" :key="a.id" :href="`/accounting/banking/${a.id}`" class="card group flex flex-col gap-3 p-4 transition hover:border-brand-500">
            <div class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-lg bg-brand-50 text-brand-700 dark:text-white"><Icon :name="icons[a.type]" /></span>
                <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ a.name }}</span><span class="block text-xs capitalize text-ink-3">{{ a.type }}{{ a.account_number ? ` · ${a.account_number}` : '' }}</span></span>
            </div>
            <p class="num text-2xl font-semibold" :class="a.balance < 0 && 'text-negative'">{{ money(a.balance) }}</p>
            <div class="flex items-center justify-between text-xs text-ink-3">
                <span>{{ a.movements }} movements</span>
                <Badge v-if="a.last_reconciliation" :tone="a.last_reconciliation.status === 'matched' ? 'positive' : 'warning'">{{ a.last_reconciliation.status === 'matched' ? 'Reconciled' : 'Difference' }} {{ date(a.last_reconciliation.statement_date) }}</Badge>
                <Badge v-else tone="neutral">Not reconciled</Badge>
            </div>
        </Link>
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <Link href="/accounting/banking/unassigned" class="card flex flex-col gap-2 p-4 hover:border-brand-500 lg:col-span-2">
            <div class="flex items-center justify-between"><p class="font-semibold">Unassigned movements</p><Badge tone="warning">{{ balances.unassigned.movements }} entries</Badge></div>
            <p class="text-sm text-ink-2">Historical entries recorded without an account (most pre-October salaries and bills). They still count in book cash; record future payments with an account to keep each balance reconcilable.</p>
            <p class="num text-xl font-semibold" :class="balances.unassigned.balance < 0 && 'text-negative'">{{ money(balances.unassigned.balance) }}</p>
        </Link>
        <Card title="Total book cash" :subtitle="`As of ${date(balances.as_of)}`">
            <p class="num text-3xl font-semibold" :class="balances.total < 0 && 'text-negative'">{{ money(balances.total) }}</p>
            <p class="mt-2 text-xs text-ink-3">Equals the statement's ending cash for the month — accounts + unassigned.</p>
        </Card>
    </div>
    <Drawer v-model="open" title="Add account" subtitle="Aliases are the words used in the Account / Method fields (comma separated)">
        <form id="bank-form" class="space-y-4" @submit.prevent="form.post('/accounting/banking', { onSuccess: () => { open = false; form.reset(); } })">
            <Input v-model="form.name" label="Name" required :error="form.errors.name" />
            <div class="grid grid-cols-2 gap-3"><Select v-model="form.type" label="Type" :options="[{ value: 'bank', label: 'Bank' }, { value: 'wallet', label: 'Mobile wallet' }, { value: 'cash', label: 'Cash' }]" /><Input v-model="form.account_number" label="Account number" /></div>
            <Input v-model="form.aliases" label="Aliases" placeholder="e.g. city bank, cbl" />
            <div class="grid grid-cols-2 gap-3"><MoneyInput v-model="form.opening_balance" label="Opening balance" /><Input v-model="form.opening_date" type="date" label="Opening date" /></div>
        </form>
        <template #footer><Button variant="secondary" @click="open = false">Cancel</Button><Button type="submit" form="bank-form" :loading="form.processing">Save</Button></template>
    </Drawer>
</template>
