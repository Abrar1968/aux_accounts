<?php

namespace App\Services\Mail;

/** Byte stream to an IMAP server (TLS socket in production, scripted fake in tests). */
interface ImapTransport
{
    public function write(string $data): void;

    /** One line including its CRLF ('' at end of stream). */
    public function readLine(): string;

    /** Exactly $bytes bytes (an IMAP literal). */
    public function read(int $bytes): string;

    public function close(): void;
}
