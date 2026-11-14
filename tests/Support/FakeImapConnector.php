<?php

namespace Tests\Support;

use App\Services\Mail\ImapClient;
use App\Services\Mail\ImapConnector;

class FakeImapConnector extends ImapConnector
{
    public ?FakeImapTransport $last = null;

    public function __construct(public FakeImapTransport|\Closure $transport, public string $password = 'secret') {}

    public function configured(): bool
    {
        return true;
    }

    public function connect(): ImapClient
    {
        $this->last = $this->transport instanceof \Closure ? ($this->transport)() : $this->transport;
        $client = new ImapClient($this->last);
        $client->login('admin@accounts.auxtechsoftware.com', $this->password);

        return $client;
    }
}
