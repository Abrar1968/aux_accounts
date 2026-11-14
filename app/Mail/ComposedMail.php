<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/** Email written in the admin composer; body is pre-rendered by EmailComposerService. */
class ComposedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param list<string> $references */
    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $fromName,
        public ?string $messageId = null,
        public ?string $inReplyTo = null,
        public array $references = [],
    ) {}

    /** Message-ID / In-Reply-To / References let the Inbox thread client replies with this email. */
    public function headers(): Headers
    {
        return new Headers(
            messageId: $this->messageId,
            references: $this->references,
            text: $this->inReplyTo ? ['In-Reply-To' => '<'.$this->inReplyTo.'>'] : [],
        );
    }

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
