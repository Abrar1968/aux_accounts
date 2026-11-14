<?php

namespace Tests\Support;

use App\Services\Mail\ImapTransport;

/** Scripted IMAP server: answers LOGIN / EXAMINE / UID SEARCH / UID FETCH / LOGOUT like Dovecot. */
class FakeImapTransport implements ImapTransport
{
    private string $buffer = "* OK [CAPABILITY IMAP4rev1] Dovecot ready.\r\n";

    /** @var list<string> */
    public array $commands = [];

    /** @param array<int,string> $messages uid => raw RFC 822 message */
    public function __construct(public array $messages, public string $password = 'secret', public int $uidvalidity = 777) {}

    public function write(string $data): void
    {
        $line = rtrim($data, "\r\n");
        $this->commands[] = $line;
        [$tag, $cmd] = explode(' ', $line, 2);
        $out = '';
        if (str_starts_with($cmd, 'LOGIN ')) {
            $out = str_contains($cmd, '"'.$this->password.'"') ? "{$tag} OK Logged in\r\n" : "{$tag} NO [AUTHENTICATIONFAILED] Authentication failed.\r\n";
        } elseif (str_starts_with($cmd, 'EXAMINE ')) {
            $uids = array_keys($this->messages);
            $out = '* '.count($uids)." EXISTS\r\n* 0 RECENT\r\n* OK [UIDVALIDITY {$this->uidvalidity}] UIDs valid\r\n* OK [UIDNEXT ".((max($uids ?: [0])) + 1)."] Predicted next UID\r\n{$tag} OK [READ-ONLY] Examine completed.\r\n";
        } elseif (preg_match('/^UID SEARCH UID (\d+):\*/', $cmd, $m)) {
            $uids = array_keys($this->messages);
            $hit = array_filter($uids, fn ($u) => $u >= (int) $m[1]);
            $hit = $hit ?: ($uids ? [max($uids)] : []); // RFC 3501: n:* always includes the highest UID
            $out = '* SEARCH '.implode(' ', $hit)."\r\n{$tag} OK Search completed.\r\n";
        } elseif (str_starts_with($cmd, 'UID SEARCH ')) {
            $out = '* SEARCH '.implode(' ', array_keys($this->messages))."\r\n{$tag} OK Search completed.\r\n";
        } elseif (preg_match('/^UID FETCH ([\d,]+) \((.+)\)$/', $cmd, $m)) {
            $seq = 0;
            foreach (explode(',', $m[1]) as $uid) {
                $raw = $this->messages[(int) $uid] ?? null;
                $seq++;
                if ($raw === null) {
                    continue;
                }
                $size = strlen($raw);
                if (! str_contains($m[2], 'BODY')) {
                    $out .= "* {$seq} FETCH (UID {$uid} RFC822.SIZE {$size})\r\n";

                    continue;
                }
                $body = str_contains($m[2], 'BODY.PEEK[HEADER]') ? substr($raw, 0, strpos($raw, "\r\n\r\n") + 4) : $raw;
                $section = str_contains($m[2], 'BODY.PEEK[HEADER]') ? 'BODY[HEADER]' : 'BODY[]';
                $out .= "* {$seq} FETCH (UID {$uid} RFC822.SIZE {$size} INTERNALDATE \"14-Nov-2026 10:00:00 +0600\" {$section} {".strlen($body)."}\r\n{$body})\r\n";
            }
            $out .= "{$tag} OK Fetch completed.\r\n";
        } elseif ($cmd === 'LOGOUT') {
            $out = "* BYE Logging out\r\n{$tag} OK Logout completed.\r\n";
        } else {
            $out = "{$tag} BAD Unknown command\r\n";
        }
        $this->buffer .= $out;
    }

    public function readLine(): string
    {
        $pos = strpos($this->buffer, "\n");
        if ($pos === false) {
            $line = $this->buffer;
            $this->buffer = '';

            return $line;
        }
        $line = substr($this->buffer, 0, $pos + 1);
        $this->buffer = substr($this->buffer, $pos + 1);

        return $line;
    }

    public function read(int $bytes): string
    {
        $chunk = substr($this->buffer, 0, $bytes);
        $this->buffer = substr($this->buffer, $bytes);

        return $chunk;
    }

    public function close(): void {}
}
