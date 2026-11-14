<?php

namespace App\Services\Mail;

use RuntimeException;

/**
 * Minimal read-only IMAP4rev1 client (RFC 3501) — no extension or package needed. The mailbox is
 * opened with EXAMINE and bodies are fetched with BODY.PEEK, so nothing is changed on the server
 * (messages keep their read/unread state for other mail apps).
 */
class ImapClient
{
    private int $tag = 0;

    public function __construct(private readonly ImapTransport $io)
    {
        $greeting = $this->io->readLine();
        if (! str_starts_with($greeting, '* OK') && ! str_starts_with($greeting, '* PREAUTH')) {
            throw new RuntimeException('Unexpected mail server greeting: '.trim($greeting));
        }
    }

    public function login(string $user, string $password): void
    {
        $this->command('LOGIN '.$this->quote($user).' '.$this->quote($password), 'Mailbox login failed — check the mailbox email and password');
    }

    /** @return array{exists:int, uidvalidity:int, uidnext:int} */
    public function examine(string $folder = 'INBOX'): array
    {
        $r = $this->command('EXAMINE '.$this->quote($folder));
        $out = ['exists' => 0, 'uidvalidity' => 0, 'uidnext' => 0];
        foreach ($r['untagged'] as $line) {
            if (preg_match('/^(\d+) EXISTS/i', $line['text'], $m)) {
                $out['exists'] = (int) $m[1];
            } elseif (preg_match('/\[UIDVALIDITY (\d+)\]/i', $line['text'], $m)) {
                $out['uidvalidity'] = (int) $m[1];
            } elseif (preg_match('/\[UIDNEXT (\d+)\]/i', $line['text'], $m)) {
                $out['uidnext'] = (int) $m[1];
            }
        }

        return $out;
    }

    /** @return list<int> UIDs matching an IMAP search, e.g. "UID 41:*" or "SINCE 15-Oct-2026". */
    public function search(string $criteria): array
    {
        $r = $this->command('UID SEARCH '.$criteria);
        $uids = [];
        foreach ($r['untagged'] as $line) {
            if (preg_match('/^SEARCH\b(.*)$/i', $line['text'], $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $n) {
                    if (ctype_digit($n)) {
                        $uids[] = (int) $n;
                    }
                }
            }
        }
        sort($uids);

        return array_values(array_unique($uids));
    }

    /**
     * @param  list<int>  $uids
     * @return array<int, array{uid:int, size:int, internaldate:?string, raw:?string}>
     */
    public function fetch(array $uids, bool $headersOnly = false): array
    {
        if (! $uids) {
            return [];
        }
        $section = $headersOnly ? 'BODY.PEEK[HEADER]' : 'BODY.PEEK[]';
        $r = $this->command('UID FETCH '.implode(',', $uids).' (UID RFC822.SIZE INTERNALDATE '.$section.')');
        $out = [];
        foreach ($r['untagged'] as $line) {
            if (! preg_match('/^\d+ FETCH /i', $line['text'])) {
                continue;
            }
            $items = $this->parseList($line['text'], $line['literals'], strpos($line['text'], '('));
            $map = [];
            for ($i = 0; $i + 1 < count($items); $i += 2) {
                $key = is_string($items[$i]) ? strtoupper($items[$i]) : '';
                $map[preg_replace('/^BODY\[.*$/', 'BODY', $key)] = $items[$i + 1];
            }
            if (! isset($map['UID'])) {
                continue;
            }
            $uid = (int) $map['UID'];
            $out[$uid] = ['uid' => $uid, 'size' => (int) ($map['RFC822.SIZE'] ?? 0), 'internaldate' => is_string($map['INTERNALDATE'] ?? null) ? $map['INTERNALDATE'] : null,
                'raw' => is_string($map['BODY'] ?? null) ? $map['BODY'] : null];
        }

        return $out;
    }

    /** @return array<int,int> uid => RFC822 size */
    public function sizes(array $uids): array
    {
        if (! $uids) {
            return [];
        }
        $r = $this->command('UID FETCH '.implode(',', $uids).' (UID RFC822.SIZE)');
        $out = [];
        foreach ($r['untagged'] as $line) {
            if (preg_match('/UID (\d+)/i', $line['text'], $u) && preg_match('/RFC822\.SIZE (\d+)/i', $line['text'], $s)) {
                $out[(int) $u[1]] = (int) $s[1];
            }
        }

        return $out;
    }

    public function logout(): void
    {
        try {
            $this->command('LOGOUT');
        } catch (\Throwable) {
            // server may close first
        }
        $this->io->close();
    }

    /**
     * Send a tagged command; collect untagged responses (literals kept aside as "\0n\0" markers).
     *
     * @return array{untagged: list<array{text:string, literals:list<string>}>, status:string, text:string}
     */
    public function command(string $command, ?string $failMessage = null): array
    {
        $tag = 'A'.str_pad((string) ++$this->tag, 4, '0', STR_PAD_LEFT);
        $this->io->write($tag.' '.$command."\r\n");
        $untagged = [];
        while (true) {
            [$text, $literals] = $this->readLogicalLine();
            if (str_starts_with($text, $tag.' ')) {
                $rest = substr($text, strlen($tag) + 1);
                $status = strtoupper(strtok($rest, ' ') ?: '');
                if ($status !== 'OK') {
                    $reason = trim(substr($rest, strlen($status)));
                    throw new RuntimeException(($failMessage ? $failMessage.': ' : 'Mail server refused '.strtok($command, ' ').': ').$reason);
                }

                return ['untagged' => $untagged, 'status' => $status, 'text' => trim(substr($rest, 3))];
            }
            if (str_starts_with($text, '* ')) {
                $untagged[] = ['text' => substr($text, 2), 'literals' => $literals];
            } elseif (str_starts_with($text, '+')) {
                throw new RuntimeException('Mail server asked for unexpected input.');
            }
        }
    }

    /** @return array{0:string, 1:list<string>} one response line with its literals pulled out */
    private function readLogicalLine(): array
    {
        $text = '';
        $literals = [];
        while (true) {
            $line = $this->io->readLine();
            if ($line === '') {
                throw new RuntimeException('Mail server closed the connection.');
            }
            if (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
                $text .= substr($line, 0, -strlen($m[0]))."\0".count($literals)."\0";
                $literals[] = $this->io->read((int) $m[1]);

                continue;
            }

            return [$text.rtrim($line, "\r\n"), $literals];
        }
    }

    /**
     * Parse a parenthesised IMAP list starting at $pos ("(") into nested PHP arrays.
     *
     * @param  list<string>  $literals
     */
    public function parseList(string $s, array $literals, int|false $pos): array
    {
        if ($pos === false) {
            return [];
        }
        $i = $pos + 1;

        return $this->parseItems($s, $literals, $i);
    }

    private function parseItems(string $s, array $literals, int &$i): array
    {
        $items = [];
        $n = strlen($s);
        while ($i < $n) {
            $c = $s[$i];
            if ($c === ' ') {
                $i++;
            } elseif ($c === ')') {
                $i++;

                return $items;
            } elseif ($c === '(') {
                $i++;
                $items[] = $this->parseItems($s, $literals, $i);
            } elseif ($c === "\0") {
                $end = strpos($s, "\0", $i + 1);
                $items[] = $literals[(int) substr($s, $i + 1, $end - $i - 1)] ?? '';
                $i = $end + 1;
            } elseif ($c === '"') {
                $buf = '';
                $i++;
                while ($i < $n && $s[$i] !== '"') {
                    if ($s[$i] === '\\' && $i + 1 < $n) {
                        $i++;
                    }
                    $buf .= $s[$i++];
                }
                $i++;
                $items[] = $buf;
            } else {
                // atom, number, NIL, or section like BODY[HEADER] / BODY[]<0>
                $start = $i;
                $depth = 0;
                while ($i < $n && ($depth > 0 || ! in_array($s[$i], [' ', ')', '('], true))) {
                    $depth += $s[$i] === '[' ? 1 : ($s[$i] === ']' ? -1 : 0);
                    $i++;
                }
                $atom = substr($s, $start, $i - $start);
                $items[] = strtoupper($atom) === 'NIL' ? null : $atom;
            }
        }

        return $items;
    }

    private function quote(string $s): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $s).'"';
    }
}
