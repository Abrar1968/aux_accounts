<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email written in the admin composer; body is pre-rendered by EmailComposerService. */
class ComposedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $subjectLine, public string $body, public string $fromName) {}

    public function envelope(): Envelope
    {
        $from = (string) config('mail.from.address');

        return new Envelope(
            from: new Address($from, $this->fromName),
            replyTo: [new Address((string) (config('mail.reply_to.address') ?: $from), $this->fromName)],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->body);
    }
}
