<script setup>
// Inbox: client replies read from the company mailbox (every email sent from the app uses it as
// Reply-To). Conversations are threaded with the email they answer; reply right here.
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Icon from '@/Components/ui/Icon.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Switch from '@/Components/ui/Switch.vue';
import RecipientPicker from '@/Components/ui/RecipientPicker.vue';
import { getJson } from '@/Composables/useJson';
import { can } from '@/lib/nav';
import { dateTime, relative } from '@/lib/format';

const props = defineProps({ messages: Object, filters: Object, status: Object, selected: Object });
const user = computed(() => usePage().props.auth?.user);

// list filters (URL-synced)
const view = ref(props.filters.view ?? 'all');
const q = ref(props.filters.q ?? '');
const go = (extra = {}) => router.get('/system/inbox', Object.fromEntries(Object.entries({ view: view.value === 'all' ? undefined : view.value, q: q.value || undefined, ...extra }).filter(([, v]) => v !== undefined && v !== '')), { preserveState: true, preserveScroll: true, replace: true });
watch(view, () => go());
let t;
watch(q, () => { clearTimeout(t); t = setTimeout(() => go(), 300); });
const open = (m) => go({ message: m.id });
const back = () => go();
usePoll(60000, { only: ['messages', 'status', 'inbox'] }); // the server checks the mailbox every minute

const checking = ref(false);
const sync = () => router.post('/system/inbox/sync', {}, { preserveScroll: true, onStart: () => (checking.value = true), onFinish: () => (checking.value = false) });
const markUnread = () => router.post(`/system/inbox/${props.selected.id}/read`, { read: false }, { preserveScroll: true, onSuccess: () => back() });
const archive = (flag) => router.post(`/system/inbox/${props.selected.id}/archive`, { archive: flag }, { preserveScroll: true, onSuccess: () => back() });

// thread
const showQuote = ref({});
const showHtml = ref({});
const fmtSize = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

// reply composer
const contacts = ref([]);
if (can(user.value, 'email.send')) getJson('/system/email/contacts').then((r) => (contacts.value = r.contacts)).catch(() => {});
const replying = ref(false);
const form = useForm({ to: '', cc: '', subject: '', greeting: '', body: '', include_quote: true });
const lastIncoming = computed(() => [...(props.selected?.thread.items ?? [])].reverse().find((i) => i.direction === 'in'));
function startReply() {
    const s = props.selected;
    const firstName = String(s.from || '').split(/[\s@]/)[0];
    form.reset();
    form.clearErrors();
    Object.assign(form, { to: s.from_email, subject: /^re:/i.test(s.subject) ? s.subject : `Re: ${s.subject}`, greeting: `Dear ${firstName},`, include_quote: true });
    replying.value = true;
}
watch(() => props.selected?.id, () => (replying.value = false));
const sendReply = () => form.post(`/system/inbox/${props.selected.id}/reply`, { preserveScroll: true, onSuccess: () => { replying.value = false; form.reset(); } });

const views = computed(() => [
    { key: 'all', label: 'Inbox' }, { key: 'replies', label: 'Replies to us' }, { key: 'unread', label: 'Unread', count: props.status.unread }, { key: 'archived', label: 'Archived' },
]);
</script>

<template>
    <Head title="Inbox" />
    <PageHeader title="Inbox" :subtitle="status.configured ? `Replies to your emails arrive here · ${status.address} · checked ${status.last_sync_at ? relative(status.last_sync_at) : 'not yet'}` : 'Connect the company mailbox to read client replies here'">
        <Button variant="secondary" icon="reverse" :loading="checking" :disabled="!status.configured" @click="sync">Check now</Button>
        <Button v-if="can(user, 'email.send')" href="/system/email" icon="send">Compose</Button>
    </PageHeader>

    <div v-if="!status.configured" class="card mb-4 flex items-start gap-3 border-warning/40 p-4 text-sm">
        <Icon name="alert" class="mt-0.5 shrink-0 text-warning" />
        <p class="text-ink-2">The mailbox login is missing. Set <b class="text-ink">MAIL_HOST</b>, <b class="text-ink">MAIL_USERNAME</b> and <b class="text-ink">MAIL_PASSWORD</b> in <code>.env</code> (or <code>IMAP_HOST</code> / <code>IMAP_USERNAME</code> / <code>IMAP_PASSWORD</code> for a different mailbox), then run <code>artisan optimize</code>.</p>
    </div>
    <div v-else-if="status.last_error" class="card mb-4 flex items-start gap-3 !border-negative/40 p-4 text-sm">
        <Icon name="alert" class="mt-0.5 shrink-0 text-negative" />
        <p class="min-w-0 text-ink-2"><b class="text-negative">Last check failed</b> {{ status.last_error_at ? relative(status.last_error_at) : '' }} — {{ status.last_error }}</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(320px,420px)_minmax(0,1fr)]">
        <!-- message list -->
        <Card :padded="false" :class="selected && 'max-lg:hidden'" class="h-fit">
            <div class="space-y-2 border-b border-line p-3">
                <Tabs v-model="view" :tabs="views" />
                <label class="relative block">
                    <Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
                    <input v-model="q" type="search" data-page-search placeholder="Search sender, subject, text…" aria-label="Search inbox" class="field !h-9 !rounded-full pl-9 pr-3" @keydown.esc="q = ''" />
                </label>
            </div>
            <EmptyState v-if="!messages.data.length" icon="inbox" :title="q ? 'No matches' : 'Nothing here yet'"
                :message="q ? `No email matches “${q}”.` : status.configured ? 'Replies to emails you send from the app will appear here within a minute.' : 'Connect the mailbox to start.'" />
            <ul v-else class="divide-y divide-line">
                <li v-for="m in messages.data" :key="m.id">
                    <button type="button" :aria-current="selected?.id === m.id ? 'true' : undefined" @click="open(m)"
                        :class="selected?.id === m.id ? 'bg-brand-50 shadow-[inset_3px_0_0_var(--color-brand-400)]' : 'hover:bg-ink/[0.04]'"
                        class="group flex w-full gap-3 px-4 py-3 text-left transition-colors">
                        <Avatar :name="m.from_name || m.from_email" size="md" />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline gap-2">
                                <span :class="!m.read_at ? 'font-bold text-ink' : 'font-medium text-ink-2'" class="min-w-0 flex-1 truncate text-sm">{{ m.from_name || m.from_email }}</span>
                                <span class="shrink-0 font-mono text-[10.5px] text-ink-3">{{ relative(m.sent_at) }}</span>
                            </span>
                            <span :class="!m.read_at ? 'font-semibold text-ink' : 'text-ink-2'" class="block truncate text-[13px]">{{ m.subject }}</span>
                            <span class="block truncate text-xs text-ink-3">{{ m.snippet }}</span>
                            <span v-if="m.sent_email || m.has_attachments || m.replied_at" class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <Badge v-if="m.sent_email" tone="brand" :dot="false"><Icon name="reverse" :size="11" />Reply to our email</Badge>
                                <Badge v-if="m.replied_at" tone="positive" :dot="false"><Icon name="check" :size="11" />Answered</Badge>
                                <Icon v-if="m.has_attachments" name="link" :size="13" class="text-ink-3" aria-label="Has attachments" />
                            </span>
                        </span>
                        <span v-if="!m.read_at" class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-400 shadow-[0_0_8px_var(--color-brand-400)]" aria-label="Unread" />
                    </button>
                </li>
            </ul>
            <Pagination :page="messages" />
        </Card>

        <!-- conversation -->
        <div class="min-w-0" :class="!selected && 'max-lg:hidden'">
            <Card v-if="!selected" class="lg:sticky lg:top-20"><EmptyState icon="mail" title="Select a message" message="Open an email to read the whole conversation — your original email and every reply — and answer it from here." /></Card>
            <template v-else>
                <Card :padded="false">
                    <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-start sm:px-5">
                        <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full border border-line text-ink-2 hover:bg-ink/5 lg:hidden" aria-label="Back to list" @click="back"><Icon name="arrow-left" :size="16" /></button>
                        <div class="min-w-0 flex-1">
                            <p class="eyebrow text-brand-600">{{ selected.thread.root ? 'Conversation' : 'Message' }} · {{ selected.thread.items.length }}</p>
                            <h2 class="mt-1 text-lg font-bold tracking-[-0.02em]">{{ selected.thread.subject }}</h2>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button v-if="can(user, 'email.inbox')" size="sm" icon="send" @click="startReply">Reply</Button>
                            <Button size="sm" variant="secondary" icon="eye" @click="markUnread">Mark unread</Button>
                            <Button size="sm" variant="ghost" :icon="selected.archived ? 'inbox' : 'box'" @click="archive(!selected.archived)">{{ selected.archived ? 'Unarchive' : 'Archive' }}</Button>
                        </div>
                    </div>
                    <ol class="space-y-3 p-4 sm:p-5">
                        <li v-for="i in selected.thread.items" :key="i.key" :class="i.direction === 'out' ? 'sm:ml-10 border-brand-400/30 bg-brand-50' : 'sm:mr-10 border-line bg-ink/[0.02]'" class="rounded-2xl border p-4 transition hover:border-line-strong">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <Avatar :name="i.direction === 'out' ? 'Auxtech' : i.from" size="sm" />
                                <p class="min-w-0 text-sm font-semibold">{{ i.direction === 'out' ? `${i.from} · sent from Auxtech Accounts` : i.from }}<span v-if="i.direction === 'in' && i.from_email !== i.from" class="font-normal text-ink-3"> &lt;{{ i.from_email }}&gt;</span></p>
                                <Badge v-if="i.direction === 'out'" :tone="i.status === 'sent' ? 'brand' : 'negative'" :label="i.status === 'sent' ? 'Sent' : 'Failed'" />
                                <span class="ml-auto font-mono text-[11px] text-ink-3">{{ dateTime(i.at) }}</span>
                            </div>
                            <p class="mt-1 truncate text-xs text-ink-3">To {{ i.to }}<template v-if="i.cc"> · Cc {{ i.cc }}</template></p>
                            <iframe v-if="showHtml[i.key]" :src="`/system/inbox/${i.id}/html`" sandbox="" title="Original email" class="mt-3 h-[60vh] w-full rounded-xl bg-white" />
                            <div v-else class="mt-3 whitespace-pre-wrap break-words text-[14px] leading-relaxed text-ink">{{ i.body || '(no text)' }}</div>
                            <div v-if="i.quote && showQuote[i.key] && !showHtml[i.key]" class="mt-3 whitespace-pre-wrap break-words border-l-2 border-line-strong pl-3 text-[13px] leading-relaxed text-ink-3">{{ i.quote }}</div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button v-if="i.quote && !showHtml[i.key]" type="button" class="rounded-full border border-line px-2.5 py-1 text-xs font-medium text-ink-3 transition hover:border-brand-400/50 hover:text-ink" @click="showQuote[i.key] = !showQuote[i.key]">{{ showQuote[i.key] ? 'Hide' : 'Show' }} quoted text</button>
                                <button v-if="i.has_html" type="button" class="rounded-full border border-line px-2.5 py-1 text-xs font-medium text-ink-3 transition hover:border-brand-400/50 hover:text-ink" @click="showHtml[i.key] = !showHtml[i.key]">{{ showHtml[i.key] ? 'Plain text' : 'Original layout' }}</button>
                                <a v-for="a in i.attachments" :key="a.id" :href="`/system/inbox/attachments/${a.id}`" class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-line bg-surface/50 px-2.5 py-1 text-xs font-medium text-ink-2 transition hover:-translate-y-px hover:border-brand-400/60 hover:text-ink">
                                    <Icon name="download" :size="13" /><span class="truncate">{{ a.name }}</span><span class="font-mono text-[10px] text-ink-3">{{ fmtSize(a.size) }}</span>
                                </a>
                            </div>
                        </li>
                    </ol>
                </Card>

                <Card v-if="replying" class="mt-4" title="Reply" :subtitle="`Signed as you set in Settings · threaded with ${lastIncoming?.from ?? selected.from}`" icon="send">
                    <form class="space-y-3" @submit.prevent="sendReply">
                        <RecipientPicker v-model="form.to" :contacts="contacts" label="To" :error="form.errors.to" />
                        <RecipientPicker v-model="form.cc" :contacts="contacts" label="CC (optional)" :error="form.errors.cc" placeholder="Copy someone in…" />
                        <Input v-model="form.subject" label="Subject" :error="form.errors.subject" />
                        <Input v-model="form.greeting" label="Greeting" :error="form.errors.greeting" />
                        <Textarea v-model="form.body" :rows="6" label="Message" :error="form.errors.body" placeholder="Write your reply…" />
                        <Switch v-model="form.include_quote" label="Include their message below my reply" />
                        <div class="flex flex-wrap items-center gap-2">
                            <Button type="submit" icon="send" :loading="form.processing" :disabled="!form.body.trim()">Send reply</Button>
                            <Button variant="ghost" @click="replying = false">Cancel</Button>
                            <span class="text-xs text-ink-3">Ctrl+S sends</span>
                        </div>
                    </form>
                </Card>
            </template>
        </div>
    </div>
</template>
