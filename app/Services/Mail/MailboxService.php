<?php

namespace App\Services\Mail;

use App\Exceptions\Domain\DomainException;
use App\Models\MailAttachment;
use App\Models\MailMessage;
use App\Models\SentEmail;
use App\Models\User;
use App\Notifications\MailReceived;
use App\Services\Notifications\NotificationService;
use App\Services\Shared\SettingsService;
use App\Services\System\EmailComposerService;
use App\Support\Search;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Inbox (new feature). Reads the company mailbox (the Reply-To of every email sent from the app)
 * over read-only IMAP, stores each message once, threads it with the email it answers
 * (In-Reply-To / References → sent_emails.message_id, then subject + sender as fallback) and
 * notifies the team. Replies are sent through the composer so they are branded and logged.
 */
class MailboxService
{
    private const STATE = ['mailbox.uidvalidity', 'mailbox.last_uid', 'mailbox.last_sync_at', 'mailbox.last_error', 'mailbox.last_error_at'];

    public function __construct(
        private readonly ImapConnector $connector,
        private readonly MimeParser $parser,
        private readonly SettingsService $settings,
        private readonly NotificationService $notifications,
    ) {}

    /** @return array{new:int, linked:int, skipped:bool, error:?string} */
    public function sync(int $limit = 40): array
    {
        if (! $this->connector->configured()) {
            return ['new' => 0, 'linked' => 0, 'skipped' => true, 'error' => null];
        }
        $folder = (string) config('mail.imap.folder', 'INBOX');
        $new = $linked = 0;
        $client = null;
        try {
            $client = $this->connector->connect();
            $box = $client->examine($folder);
            $lastUid = (int) $this->settings->get('mailbox.last_uid', 0);
            if ((int) $this->settings->get('mailbox.uidvalidity', 0) !== $box['uidvalidity']) {
                $lastUid = 0; // mailbox was rebuilt on the server — re-scan (duplicates are skipped by Message-ID)
            }
            $uids = $lastUid === 0
                ? $client->search('SINCE '.now()->subDays((int) config('mail.imap.initial_days', 30))->format('j-M-Y'))
                : array_values(array_filter($client->search('UID '.($lastUid + 1).':*'), fn ($u) => $u > $lastUid));
            $uids = array_slice($uids, 0, $limit);
            $max = (int) config('mail.imap.max_message_mb', 20) * 1048576;
            $sizes = $client->sizes($uids);
            $stored = [];
            foreach (array_chunk($uids, 10) as $batch) {
                $big = array_values(array_filter($batch, fn ($u) => ($sizes[$u] ?? 0) > $max));
                $small = array_values(array_diff($batch, $big));
                foreach ([[$small, false], [$big, true]] as [$set, $headersOnly]) {
                    foreach ($client->fetch($set, $headersOnly) as $uid => $f) {
                        $m = $f['raw'] === null ? null : $this->store($folder, $box['uidvalidity'], $uid, $f['raw'], $f['internaldate'], $f['size'], $headersOnly);
                        if ($m) {
                            $stored[] = $m;
                            $new++;
                            $linked += $m->sent_email_id ? 1 : 0;
                        }
                        $lastUid = max($lastUid, $uid);
                    }
                }
                $lastUid = max($lastUid, ...$batch);
                $this->settings->set('mailbox.last_uid', $lastUid, 'mailbox');
            }
            $this->settings->set('mailbox.uidvalidity', $box['uidvalidity'], 'mailbox');
            $this->settings->set('mailbox.last_sync_at', now()->toIso8601String(), 'mailbox');
            $this->settings->set('mailbox.last_error', null, 'mailbox');
            $client->logout();
            $this->notify($stored);

            return ['new' => $new, 'linked' => $linked, 'skipped' => false, 'error' => null];
        } catch (\Throwable $e) {
            $client?->logout();
            $this->settings->set('mailbox.last_error', Str::limit($e->getMessage(), 300), 'mailbox');
            $this->settings->set('mailbox.last_error_at', now()->toIso8601String(), 'mailbox');
            report($e);

            return ['new' => $new, 'linked' => $linked, 'skipped' => false, 'error' => $e->getMessage()];
        }
    }

    public function store(string $folder, int $uidvalidity, int $uid, string $raw, ?string $internaldate, int $size, bool $headersOnly = false): ?MailMessage
    {
        if (MailMessage::query()->where(['folder' => $folder, 'uidvalidity' => $uidvalidity, 'uid' => $uid])->exists()) {
            return null;
        }
        $p = $this->parser->parse($raw);
        if ($p['message_id'] && MailMessage::query()->where('message_id', $p['message_id'])->exists()) {
            return null;
        }
        $text = $headersOnly ? '(This message is larger than the import limit — open it in webmail to read it.)' : $this->parser->plain($p['text'], $p['html']);
        $date = $this->date($p['date']) ?? $this->date($internaldate) ?? now();
        $files = $p['attachments'];

        return DB::transaction(function () use ($folder, $uidvalidity, $uid, $p, $text, $date, $size, $files) {
            $m = MailMessage::query()->create([
                'folder' => $folder, 'uidvalidity' => $uidvalidity, 'uid' => $uid,
                'message_id' => $p['message_id'] ? Str::limit($p['message_id'], 250, '') : null,
                'in_reply_to' => $p['in_reply_to'] ? Str::limit($p['in_reply_to'], 250, '') : null,
                'references' => $p['references'] ? implode(' ', $p['references']) : null,
                'from_email' => Str::limit($p['from']['email'] ?: 'unknown@unknown', 190, ''),
                'from_name' => $p['from']['name'] ? Str::limit($p['from']['name'], 190, '') : null,
                'to' => $p['to'] ?: null, 'cc' => $p['cc'] ?: null,
                'subject' => Str::limit($p['subject'] !== '' ? $p['subject'] : '(no subject)', 495),
                'sent_at' => $date, 'size' => $size,
                'snippet' => Str::limit(preg_replace('/\s+/u', ' ', $this->parser->splitQuote($text)[0]) ?? '', 290),
                'text_body' => $text, 'html_body' => $p['html'],
                'has_attachments' => (bool) array_filter($files, fn ($a) => ! $a['inline']),
                // our own copies (e.g. sent to ourselves) arrive read
                'read_at' => strtolower($p['from']['email']) === strtolower((string) config('mail.from.address')) ? now() : null,
            ]);
            foreach (array_values($files) as $i => $a) {
                $path = "mail/{$m->id}/".($i + 1).'-'.$a['filename'];
                Storage::disk('local')->put($path, $a['content']);
                MailAttachment::query()->create(['mail_message_id' => $m->id, 'filename' => $a['filename'], 'mime' => Str::limit($a['mime'], 118, ''),
                    'size' => strlen($a['content']), 'content_id' => $a['content_id'] ? Str::limit($a['content_id'], 250, '') : null, 'inline' => $a['inline'], 'path' => $path]);
            }
            $m->update(['sent_email_id' => $this->threadRootFor($m)]);

            return $m;
        });
    }

    /** sent_emails id this message belongs to (null = a new conversation). */
    public function threadRootFor(MailMessage $m): ?int
    {
        $ids = array_values(array_filter(array_merge([$m->in_reply_to], explode(' ', (string) $m->references))));
        if ($ids) {
            $sent = SentEmail::query()->whereIn('message_id', $ids)->latest('id')->first();
            if ($sent) {
                return $sent->thread_root_id ?: $sent->id;
            }
            $prev = MailMessage::query()->whereIn('message_id', $ids)->whereNotNull('sent_email_id')->latest('id')->value('sent_email_id');
            if ($prev) {
                return (int) $prev;
            }
        }
        $subject = $this->baseSubject($m->subject);
        if ($subject === '' || $subject === $m->subject) {
            return null; // only "Re:/Fwd:" subjects are matched by text
        }

        $match = SentEmail::query()->where('status', 'sent')->where('to', 'like', '%'.$m->from_email.'%')->latest('id')->limit(200)->get(['id', 'subject', 'thread_root_id'])
            ->first(fn (SentEmail $s) => mb_strtolower($this->baseSubject($s->subject)) === mb_strtolower($subject));

        return $match ? ($match->thread_root_id ?: $match->id) : null;
    }

    public function baseSubject(string $s): string
    {
        do {
            $before = $s;
            $s = trim(preg_replace('/^\s*((re|fwd?|aw|sv|wg)(\[\d+\])?\s*:\s*)/i', '', $s) ?? $s);
        } while ($s !== $before);

        return $s;
    }

    /** @param list<MailMessage> $stored */
    private function notify(array $stored): void
    {
        $fresh = array_values(array_filter($stored, fn (MailMessage $m) => $m->read_at === null));
        if (! $fresh) {
            return;
        }
        if (count($fresh) > 3) {
            $this->notifications->send(new MailReceived(count($fresh).' new emails', 'New messages in the inbox', 'info', '/system/inbox'));

            return;
        }
        foreach ($fresh as $m) {
            $who = $m->from_name ?: $m->from_email;
            $this->notifications->send(new MailReceived($m->sent_email_id ? "Reply from {$who}" : "New email from {$who}", Str::limit($m->subject.' — '.$m->snippet, 160), $m->sent_email_id ? 'success' : 'info', '/system/inbox?message='.$m->id));
        }
    }

    // ------------------------------------------------------------------ reading

    public function list(array $filters): LengthAwarePaginator
    {
        $view = $filters['view'] ?? 'all';

        return MailMessage::query()->with('sentEmail:id,subject,template')
            ->when($view === 'archived', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($view === 'replies', fn ($q) => $q->whereNotNull('sent_email_id'))
            ->when($view === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($filters['q'] ?? null, fn ($q, $s) => Search::apply($q, $s, ['from_email', 'from_name', 'subject', 'snippet', 'to']))
            ->orderByDesc('sent_at')->orderByDesc('id')
            ->paginate(25, ['id', 'from_email', 'from_name', 'subject', 'snippet', 'sent_at', 'read_at', 'replied_at', 'archived_at', 'has_attachments', 'sent_email_id'])
            ->withQueryString();
    }

    public function unreadCount(): int
    {
        return MailMessage::query()->whereNull('read_at')->whereNull('archived_at')->count();
    }

    /** Conversation around a message: the email we sent, every reply in, every reply out, by date. */
    public function thread(MailMessage $m): array
    {
        $root = $m->sent_email_id;
        $incoming = $root ? MailMessage::query()->with('attachments')->where('sent_email_id', $root)->get() : collect([$m->load('attachments')]);
        $outgoing = $root ? SentEmail::query()->with('user:id,name')->where(fn ($q) => $q->where('id', $root)->orWhere('thread_root_id', $root))->get() : collect();
        $items = $incoming->map(fn (MailMessage $x) => $this->presentIncoming($x))
            ->concat($outgoing->map(fn (SentEmail $s) => $this->presentOutgoing($s)))
            ->sortBy('at')->values()->all();

        return ['root' => $root, 'items' => $items, 'subject' => $this->baseSubject($m->subject) ?: $m->subject];
    }

    private function presentIncoming(MailMessage $m): array
    {
        [$body, $quote] = $this->parser->splitQuote((string) $m->text_body);

        return [
            'key' => 'in-'.$m->id, 'id' => $m->id, 'direction' => 'in', 'at' => optional($m->sent_at)->toIso8601String(),
            'from' => $m->from_name ?: $m->from_email, 'from_email' => $m->from_email, 'to' => $m->to, 'cc' => $m->cc, 'subject' => $m->subject,
            'body' => $body, 'quote' => $quote, 'has_html' => $m->html_body !== null,
            'attachments' => $m->attachments->where('inline', false)->values()->map(fn (MailAttachment $a) => ['id' => $a->id, 'name' => $a->filename, 'size' => $a->size, 'mime' => $a->mime])->all(),
        ];
    }

    private function presentOutgoing(SentEmail $s): array
    {
        $c = $s->content ?? [];
        $details = collect($c['details'] ?? [])->filter(fn ($d) => ! empty($d['on']))->map(fn ($d) => "{$d['label']}: {$d['value']}")->implode("\n");

        return [
            'key' => 'out-'.$s->id, 'id' => $s->id, 'direction' => 'out', 'at' => optional($s->sent_at ?? $s->created_at)->toIso8601String(),
            'from' => $s->user?->name ?? 'Auxtech', 'to' => $s->to, 'cc' => $s->cc, 'subject' => $s->subject, 'status' => $s->status,
            'body' => trim(implode("\n\n", array_filter([$c['greeting'] ?? null, $c['intro'] ?? null, $details ?: null, $c['closing'] ?? null]))),
            'quote' => '', 'has_html' => false, 'attachments' => [],
        ];
    }

    /** Sandboxed HTML view (inline cid: images embedded as data URIs; scripts never run). */
    public function html(MailMessage $m): string
    {
        $html = (string) $m->html_body;
        foreach ($m->attachments()->whereNotNull('content_id')->get() as $a) {
            if ($a->size <= 3 * 1048576 && Storage::disk('local')->exists($a->path)) {
                $html = str_ireplace('cid:'.$a->content_id, 'data:'.$a->mime.';base64,'.base64_encode((string) Storage::disk('local')->get($a->path)), $html);
            }
        }
        $csp = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data: https: http:; style-src \'unsafe-inline\'; font-src data: https:">';

        return $csp.'<base target="_blank">'.$html;
    }

    public function markRead(MailMessage $m, bool $read = true): void
    {
        $m->update(['read_at' => $read ? ($m->read_at ?? now()) : null]);
    }

    public function archive(MailMessage $m, bool $archive = true): void
    {
        $m->update(['archived_at' => $archive ? now() : null, 'read_at' => $m->read_at ?? now()]);
    }

    /** Reply from the app: branded, signed, threaded (In-Reply-To / References), logged in Sent emails. */
    public function reply(MailMessage $m, array $data, User $sender): SentEmail
    {
        $refs = array_values(array_filter(array_merge(explode(' ', (string) $m->references), [$m->message_id])));
        $quoted = (string) $m->text_body;
        $sent = app(EmailComposerService::class)->send([
            'template' => 'reply', 'record_ref' => 'mail:'.$m->id,
            'to' => $data['to'], 'cc' => $data['cc'] ?? '', 'subject' => $data['subject'],
            'greeting' => $data['greeting'] ?? '', 'intro' => $data['body'], 'closing' => '', 'details' => [], 'include_table' => false,
            'quote' => ($data['include_quote'] ?? true) ? Str::limit(trim($quoted), 3000) : null,
            'quote_header' => 'On '.optional($m->sent_at)->format('d M Y, H:i').', '.($m->from_name ?: $m->from_email).' wrote:',
        ], $sender, [
            'in_reply_to' => $m->message_id, 'references' => array_slice($refs, -10),
            'thread_root_id' => $m->sent_email_id, 'reply_to_mail_id' => $m->id,
        ]);
        $m->update(['sent_email_id' => $m->sent_email_id ?? $sent->id, 'replied_at' => now(), 'read_at' => $m->read_at ?? now()]);

        return $sent;
    }

    /** @return array{configured:bool, address:?string, last_sync_at:?string, last_error:?string, last_error_at:?string, unread:int, total:int} */
    public function status(): array
    {
        return [
            'configured' => $this->connector->configured(),
            'address' => config('mail.imap.username'),
            'last_sync_at' => $this->settings->get('mailbox.last_sync_at'),
            'last_error' => $this->settings->get('mailbox.last_error'),
            'last_error_at' => $this->settings->get('mailbox.last_error_at'),
            'unread' => $this->unreadCount(),
            'total' => MailMessage::query()->count(),
        ];
    }

    public function assertConfigured(): void
    {
        if (! $this->connector->configured()) {
            throw new DomainException('The mailbox is not configured: set MAIL_HOST, MAIL_USERNAME and MAIL_PASSWORD (or IMAP_*) in .env.');
        }
    }

    private function date(?string $v): ?CarbonImmutable
    {
        if (! $v) {
            return null;
        }
        try {
            return CarbonImmutable::parse(preg_replace('/\s*\([^)]*\)\s*$/', '', $v))->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<string> settings keys used for sync state (hidden from the Settings page) */
    public static function stateKeys(): array
    {
        return self::STATE;
    }
}
