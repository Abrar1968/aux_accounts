<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\ReplyMailRequest;
use App\Models\MailAttachment;
use App\Models\MailMessage;
use App\Services\Mail\MailboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Inbox: replies to emails sent from the app, read from the company mailbox (IMAP). */
class InboxController extends Controller
{
    public function __construct(private readonly MailboxService $mailbox) {}

    public function index(Request $request): Response
    {
        $filters = $request->only('q', 'view');
        $selected = $request->integer('message') ? MailMessage::query()->find($request->integer('message')) : null;
        if ($selected) {
            $this->mailbox->markRead($selected);
        }

        return Inertia::render('System/Inbox', [
            'messages' => $this->mailbox->list($filters),
            'filters' => $filters + ['view' => 'all'],
            'status' => $this->mailbox->status(),
            'selected' => $selected ? [
                'id' => $selected->id, 'from' => $selected->from_name ?: $selected->from_email, 'from_email' => $selected->from_email,
                'subject' => $selected->subject, 'to' => $selected->to, 'cc' => $selected->cc, 'read' => $selected->read_at !== null,
                'archived' => $selected->archived_at !== null, 'replied_at' => $selected->replied_at?->toIso8601String(),
                'thread' => $this->mailbox->thread($selected),
            ] : null,
        ]);
    }

    public function sync()
    {
        $this->mailbox->assertConfigured();
        $r = $this->mailbox->sync(60);
        if ($r['error']) {
            return $this->toast('Could not check the mailbox: '.$r['error'], 'error', 'Mailbox');
        }

        return $this->toast($r['new'] ? "{$r['new']} new email".($r['new'] > 1 ? 's' : '').($r['linked'] ? " · {$r['linked']} repl".($r['linked'] > 1 ? 'ies' : 'y').' to our emails' : '').'.' : 'No new email.', 'success', 'Mailbox checked');
    }

    public function read(Request $request, MailMessage $message)
    {
        $this->mailbox->markRead($message, $request->boolean('read', true));

        return back();
    }

    public function archive(Request $request, MailMessage $message)
    {
        $this->mailbox->archive($message, $request->boolean('archive', true));

        return $this->toast($request->boolean('archive', true) ? 'Archived.' : 'Moved back to the inbox.');
    }

    public function reply(ReplyMailRequest $request, MailMessage $message)
    {
        $sent = $this->mailbox->reply($message, $request->validated(), $request->user());

        return $this->toast("Reply sent to {$sent->to}.", 'success', 'Email sent');
    }

    /** Original HTML of a message, served sandboxed (no scripts, no same-origin access). */
    public function html(MailMessage $message)
    {
        abort_if($message->html_body === null, 404);

        return response($this->mailbox->html($message), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src data: https: http:; style-src 'unsafe-inline'; font-src data: https:",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function attachment(MailAttachment $attachment)
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->filename, ['Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff']);
    }
}
