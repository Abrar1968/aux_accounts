<script setup>
// "Record Payment" for an expense bill or payroll row (PaymentPostingService). The request_id
// is generated when the drawer opens, so a double click / retry can never post twice.
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Drawer from '@/Components/ui/Drawer.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import { newRequestId } from '@/Composables/useRequestId';
import { money, todayIso } from '@/lib/format';

const props = defineProps({ url: String, title: String, subtitle: String, remaining: Number });
const open = defineModel({ type: Boolean });
const form = useForm({ amount: '', paid_on: todayIso(), method: 'Bank Transfer', account: 'DBBL', notes: '', request_id: '' });
watch(open, (v) => { if (v) { form.reset(); form.clearErrors(); form.request_id = newRequestId(); form.amount = props.remaining > 0 ? props.remaining : ''; } });
const submit = () => form.post(props.url, { preserveScroll: true, onSuccess: () => (open.value = false) });
</script>

<template>
    <Drawer v-model="open" :title="title" :subtitle="subtitle">
        <form id="pay-form" class="space-y-4" @submit.prevent="submit">
            <div class="rounded-lg bg-surface-2 px-4 py-3 text-sm"><span class="text-ink-2">Remaining balance</span><span class="num float-right font-semibold">{{ money(remaining, { decimals: true }) }}</span></div>
            <MoneyInput v-model="form.amount" label="Amount paid" required :error="form.errors.amount" />
            <Input v-model="form.paid_on" type="date" label="Actual payment date" required :max="todayIso()" :error="form.errors.paid_on" hint="The real cash date — the accrual month stays on the bill." />
            <div class="grid grid-cols-2 gap-3">
                <Select v-model="form.method" label="Method" :options="['Bank Transfer', 'Cash', 'Bkash', 'Nagad', 'Card', 'Cheque']" />
                <Input v-model="form.account" label="Account / reference" />
            </div>
            <Textarea v-model="form.notes" label="Note" :rows="2" />
        </form>
        <template #footer>
            <Button variant="secondary" @click="open = false">Cancel</Button>
            <Button type="submit" form="pay-form" :loading="form.processing" icon="check">Record payment</Button>
        </template>
    </Drawer>
</template>
