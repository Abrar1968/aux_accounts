<?php

namespace App\Services\Mail;

use RuntimeException;

/** Opens a logged-in IMAP session from config('mail.imap'); swapped for a fake in tests. */
class ImapConnector
{
    public function configured(): bool
    {
        $c = config('mail.imap');

        return ! empty($c['host']) && ! empty($c['username']) && ! empty($c['password']);
    }

    public function connect(): ImapClient
    {
        if (! $this->configured()) {
            throw new RuntimeException('The mailbox is not configured (IMAP host, username and password).');
        }
        $c = config('mail.imap');
        $client = new ImapClient(new SocketTransport((string) $c['host'], (int) $c['port'], (string) $c['encryption'], (bool) $c['verify_peer']));
        $client->login((string) $c['username'], (string) $c['password']);

        return $client;
    }
}
