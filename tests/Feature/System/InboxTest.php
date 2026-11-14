<?php

namespace Tests\Feature\System;

use App\Mail\ComposedMail;
use App\Models\MailMessage;
use App\Models\SentEmail;
use App\Notifications\MailReceived;
use App\Services\Mail\ImapConnector;
use App\Services\Mail\MailboxService;
use App\Services\Mail\MimeParser;
use App\Services\System\EmailComposerService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsWorkbook;
use Tests\Support\FakeImapConnector;
use Tests\Support\FakeImapTransport;
use Tests\TestCase;

/** Inbox: read-only IMAP import, MIME decoding, threading with sent emails, replies. */
class InboxTest extends TestCase
{
    use SeedsWorkbook;

    private SentEmail $sent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-13 10:00:00'); // our email goes out the day before the client answers
        Storage::fake('local');
        Mail::fake();
        $this->sent = app(EmailComposerService::class)->send([
            'template' => 'custom', 'to' => 'client@example.com', 'subject' => 'Payment received — PAY-1 · Auxtech',
            'greeting' => 'Dear Jahan,', 'intro' => 'We received your payment.', 'closing' => '', 'details' => [],
        ], $this->owner());
        $this->travelTo('2026-11-14 12:00:00');
    }

    private function reply(string $inReplyTo): string
    {
        $subject = '=?UTF-8?B?'.base64_encode('Re: Payment received — PAY-1 · Auxtech ধন্যবাদ').'?=';
        $text = quoted_printable_encode("Thanks, received. আপনাকে ধন্যবাদ!\r\n\r\nOn Fri, 13 Nov 2026 at 10:00, Auxtech wrote:\r\n> We received your payment.");
        $pdf = base64_encode('%PDF-1.4 fake receipt');

        return "Return-Path: <client@example.com>\r\nFrom: \"Md. Jahan Mia\" <Client@Example.com>\r\nTo: admin@accounts.auxtechsoftware.com\r\n"
            ."Subject: {$subject}\r\nDate: Sat, 14 Nov 2026 11:30:00 +0600\r\nMessage-ID: <reply-1@example.com>\r\nIn-Reply-To: <{$inReplyTo}>\r\n"
            ."References: <{$inReplyTo}>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"MIX\"\r\n\r\n"
            ."--MIX\r\nContent-Type: multipart/alternative;\r\n boundary=\"ALT\"\r\n\r\n"
            ."--ALT\r\nContent-Type: text/plain; charset=\"UTF-8\"\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n{$text}\r\n"
            ."--ALT\r\nContent-Type: text/html; charset=\"UTF-8\"\r\n\r\n<div>Thanks, received.</div><img src=\"cid:logo@x\">\r\n--ALT--\r\n"
            ."--MIX\r\nContent-Type: image/png\r\nContent-ID: <logo@x>\r\nContent-Disposition: inline\r\nContent-Transfer-Encoding: base64\r\n\r\n".base64_encode('PNGDATA')."\r\n"
            ."--MIX\r\nContent-Type: application/pdf; name=\"receipt.pdf\"\r\nContent-Disposition: attachment; filename*=UTF-8''r%C3%A9sum%C3%A9.pdf\r\n"
            ."Content-Transfer-Encoding: base64\r\n\r\n{$pdf}\r\n--MIX--\r\n";
    }

    private function latin1(): string
    {
        return "From: other@shop.test\r\nTo: admin@accounts.auxtechsoftware.com\r\nSubject: =?ISO-8859-1?Q?Caf=E9_order?=\r\n"
            ."Date: Sat, 14 Nov 2026 09:00:00 +0000\r\nMessage-ID: <other-1@shop.test>\r\nContent-Type: text/plain; charset=ISO-8859-1\r\n"
            ."Content-Transfer-Encoding: 8bit\r\n\r\nUn caf\xE9, s'il vous pla\xEEt.\r\n";
    }

    private function fake(array $messages, string $password = 'secret'): FakeImapConnector
    {
        $c = new FakeImapConnector(new FakeImapTransport($messages), $password);
        $this->app->instance(ImapConnector::class, $c);

        return $c;
    }

    public function test_import_decodes_threads_stores_attachments_and_is_read_only(): void
    {
        $conn = $this->fake([1 => $this->reply($this->sent->message_id), 2 => $this->latin1()]);
        $r = app(MailboxService::class)->sync();
        $this->assertSame(['new' => 2, 'linked' => 1, 'skipped' => false, 'error' => null], $r);

        $m = MailMessage::query()->where('message_id', 'reply-1@example.com')->firstOrFail();
        $this->assertSame(['client@example.com', 'Md. Jahan Mia', $this->sent->id], [$m->from_email, $m->from_name, $m->sent_email_id]);
        $this->assertStringContainsString('ধন্যবাদ', $m->subject);
        $this->assertStringContainsString('আপনাকে ধন্যবাদ', $m->text_body);
        $this->assertSame('Thanks, received. আপনাকে ধন্যবাদ!', $m->snippet, 'quoted history kept out of the preview');
        $this->assertTrue($m->has_attachments);
        $pdf = $m->attachments()->where('inline', false)->firstOrFail();
        $this->assertSame(['résumé.pdf', '%PDF-1.4 fake receipt'], [$pdf->filename, Storage::disk('local')->get($pdf->path)]);
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode('PNGDATA'), app(MailboxService::class)->html($m), 'inline images embedded');

        $other = MailMessage::query()->where('message_id', 'other-1@shop.test')->firstOrFail();
        $this->assertSame(['Café order', null], [$other->subject, $other->sent_email_id]);
        $this->assertStringContainsString('Un café', $other->text_body);

        $this->assertTrue(collect($conn->last->commands)->contains(fn ($c) => str_contains($c, 'EXAMINE "INBOX"')), 'opened read-only');
        $this->assertTrue(collect($conn->last->commands)->contains(fn ($c) => str_contains($c, 'BODY.PEEK[]')), 'bodies fetched without marking seen');
        $notes = DatabaseNotification::query()->where('type', MailReceived::class)->get();
        $this->assertTrue($notes->contains(fn ($n) => $n->data['title'] === 'Reply from Md. Jahan Mia' && $n->data['url'] === '/system/inbox?message='.$m->id), 'team notified of the reply');

        // second run: nothing new, nothing duplicated
        $this->fake([1 => $this->reply($this->sent->message_id), 2 => $this->latin1()]);
        $this->assertSame(0, app(MailboxService::class)->sync()['new']);
        $this->assertSame(2, MailMessage::query()->count());
    }

    public function test_inbox_page_thread_and_reply_with_threading_headers(): void
    {
        $this->fake([1 => $this->reply($this->sent->message_id)]);
        app(MailboxService::class)->sync();
        $m = MailMessage::query()->firstOrFail();

        $this->actingAs($this->owner())->get('/system/inbox?message='.$m->id)->assertOk()
            ->assertInertia(fn ($p) => $p->component('System/Inbox')->where('selected.thread.root', $this->sent->id)
                ->has('selected.thread.items', 2)->where('selected.thread.items.0.direction', 'out')->where('selected.thread.items.1.direction', 'in')
                ->where('selected.thread.items.1.body', 'Thanks, received. আপনাকে ধন্যবাদ!'));
        $this->assertNotNull($m->fresh()->read_at, 'opening marks it read');

        $this->post("/system/inbox/{$m->id}/reply", ['to' => 'client@example.com', 'subject' => 'Re: Payment received', 'greeting' => 'Dear Jahan,', 'body' => 'Glad it arrived.', 'include_quote' => true])
            ->assertSessionHas('toast.type', 'success');
        $reply = SentEmail::query()->latest('id')->firstOrFail();
        $this->assertSame([$this->sent->id, $m->id, 'reply'], [$reply->thread_root_id, $reply->reply_to_mail_id, $reply->template]);
        Mail::assertSent(ComposedMail::class, fn (ComposedMail $mail) => $mail->inReplyTo === 'reply-1@example.com'
            && in_array('reply-1@example.com', $mail->references, true) && $mail->messageId === $reply->message_id
            && str_contains($mail->body, 'Glad it arrived.') && str_contains($mail->body, 'wrote:'));
        $this->assertNotNull($m->fresh()->replied_at);

        // the client answers our reply → same conversation
        $second = str_replace(['reply-1@example.com', 'In-Reply-To: <'.$this->sent->message_id.'>'], ['reply-2@example.com', 'In-Reply-To: <'.$reply->message_id.'>'], $this->reply($this->sent->message_id));
        $this->fake([1 => $this->reply($this->sent->message_id), 2 => $second]);
        app(MailboxService::class)->sync();
        $this->assertSame($this->sent->id, MailMessage::query()->where('message_id', 'reply-2@example.com')->value('sent_email_id'));

        $this->assertStringStartsWith('sandbox', (string) $this->get('/system/inbox/'.$m->id.'/html')->assertOk()->headers->get('Content-Security-Policy'));
        $this->post("/system/inbox/{$m->id}/archive", ['archive' => true]);
        $this->get('/system/inbox')->assertInertia(fn ($p) => $p->where('messages.data', fn ($d) => collect($d)->doesntContain('id', $m->id)));
    }

    public function test_subject_fallback_login_errors_and_permissions(): void
    {
        $noHeaders = "From: client@example.com\r\nSubject: RE: Payment received — PAY-1 · Auxtech\r\nMessage-ID: <x@y>\r\n\r\nok\r\n";
        $this->fake([5 => $noHeaders]);
        app(MailboxService::class)->sync();
        $this->assertSame($this->sent->id, MailMessage::query()->where('message_id', 'x@y')->value('sent_email_id'));

        $this->fake([6 => $noHeaders], 'wrong');
        $r = app(MailboxService::class)->sync();
        $this->assertStringContainsString('Mailbox login failed', (string) $r['error']);
        $this->assertStringContainsString('login failed', (string) app(MailboxService::class)->status()['last_error']);

        $this->actingAs($this->userWithRole('caller'))->get('/system/inbox')->assertForbidden();
        $this->actingAs($this->userWithRole('accountant'))->get('/system/inbox')->assertOk();
    }

    public function test_parser_edge_cases(): void
    {
        $p = app(MimeParser::class);
        $this->assertSame([['email' => 'a@b.co', 'name' => 'Doe, John'], ['email' => 'c@d.co', 'name' => null]], $p->addresses('"Doe, John" <A@b.co>, c@d.co'));
        $this->assertSame('Hello World', $p->decodeHeader('=?utf-8?Q?Hello?= =?utf-8?Q?_World?='));
        $this->assertSame(['Thanks', "On Mon, X wrote:\n> hi"], $p->splitQuote("Thanks\nOn Mon, X wrote:\n> hi"));
        $this->assertSame('Payment received', app(MailboxService::class)->baseSubject('Re: RE: Fwd: Payment received'));
    }
}
