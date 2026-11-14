<?php

namespace App\Services\Mail;

/**
 * RFC 5322 / MIME parser for inbox messages: headers (RFC 2047 encoded words, folded lines),
 * multipart trees, base64 / quoted-printable, any charset → UTF-8, attachments (RFC 2231 names).
 */
class MimeParser
{
    /**
     * @return array{headers: array<string,string>, subject:string, from: array{email:string,name:?string}, to:string, cc:string,
     *   message_id:?string, in_reply_to:?string, references: list<string>, date:?string, text:?string, html:?string,
     *   attachments: list<array{filename:string, mime:string, content:string, content_id:?string, inline:bool}>}
     */
    public function parse(string $raw): array
    {
        [$headers, $body] = $this->split($raw);
        $out = ['text' => null, 'html' => null, 'attachments' => []];
        $this->walk($headers, $body, $out);
        $from = $this->addresses($headers['from'] ?? '')[0] ?? ['email' => '', 'name' => null];

        return [
            'headers' => $headers,
            'subject' => $this->decodeHeader($headers['subject'] ?? ''),
            'from' => $from,
            'to' => implode(', ', array_column($this->addresses($headers['to'] ?? ''), 'email')),
            'cc' => implode(', ', array_column($this->addresses($headers['cc'] ?? ''), 'email')),
            'message_id' => $this->ids($headers['message-id'] ?? '')[0] ?? null,
            'in_reply_to' => $this->ids($headers['in-reply-to'] ?? '')[0] ?? null,
            'references' => $this->ids($headers['references'] ?? ''),
            'date' => $headers['date'] ?? null,
            'text' => $out['text'],
            'html' => $out['html'],
            'attachments' => $out['attachments'],
        ];
    }

    /** @return array{0: array<string,string>, 1: string} lower-cased unfolded headers + body */
    public function split(string $raw): array
    {
        $raw = ltrim($raw, "\r\n");
        $pos = strpos($raw, "\r\n\r\n");
        $sep = 4;
        $alt = strpos($raw, "\n\n");
        if ($pos === false || ($alt !== false && $alt < $pos)) {
            [$pos, $sep] = [$alt, 2];
        }
        $head = $pos === false ? $raw : substr($raw, 0, $pos);
        $body = $pos === false ? '' : substr($raw, $pos + $sep);
        $headers = [];
        $head = preg_replace("/\r?\n[ \t]+/", ' ', $head);
        foreach (preg_split("/\r?\n/", $head) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $k = strtolower(trim($k));
            $headers[$k] = isset($headers[$k]) && $k === 'received' ? $headers[$k] : trim($v); // first wins except we keep 1 Received
        }

        return [$headers, $body];
    }

    private function walk(array $headers, string $body, array &$out, int $depth = 0): void
    {
        [$type, $params] = $this->header($headers['content-type'] ?? 'text/plain; charset=us-ascii');
        $type = strtolower($type);
        if ($depth < 12 && str_starts_with($type, 'multipart/') && ! empty($params['boundary'])) {
            foreach ($this->parts($body, $params['boundary']) as $part) {
                [$h, $b] = $this->split($part);
                $this->walk($h, $b, $out, $depth + 1);
            }

            return;
        }
        [$disposition, $dparams] = $this->header($headers['content-disposition'] ?? '');
        $content = $this->decodeBody($body, strtolower(trim($headers['content-transfer-encoding'] ?? '7bit')));
        $filename = $this->paramName($dparams, 'filename') ?? $this->paramName($params, 'name');
        $isAttachment = strtolower($disposition) === 'attachment' || ($filename !== null && ! in_array($type, ['text/plain', 'text/html'], true))
            || ! in_array($type, ['text/plain', 'text/html'], true);

        if (! $isAttachment) {
            $key = $type === 'text/html' ? 'html' : 'text';
            $piece = $this->toUtf8($content, $params['charset'] ?? null);
            $out[$key] = $out[$key] === null ? $piece : $out[$key]."\n".$piece; // split bodies are joined

            return;
        }
        if ($type === 'message/delivery-status' || ($content === '' && $filename === null)) {
            return;
        }
        $cid = isset($headers['content-id']) ? trim($headers['content-id'], " <>\t") : null;
        $out['attachments'][] = [
            'filename' => $this->safeName($filename ?? ($type === 'message/rfc822' ? 'message.eml' : 'attachment-'.(count($out['attachments']) + 1).$this->ext($type))),
            'mime' => $type, 'content' => $content, 'content_id' => $cid,
            'inline' => strtolower($disposition) === 'inline' || ($cid !== null && strtolower($disposition) !== 'attachment'),
        ];
    }

    /** @return list<string> */
    private function parts(string $body, string $boundary): array
    {
        $chunks = preg_split('/\r?\n?--'.preg_quote($boundary, '/').'(?:--)?[ \t]*\r?\n?/', $body) ?: [];
        array_shift($chunks); // preamble

        return array_values(array_filter($chunks, fn ($c) => trim($c) !== ''));
    }

    /** @return array{0:string, 1: array<string,string>} value + lower-cased params (RFC 2231 continuations merged) */
    public function header(string $value): array
    {
        $parts = $this->splitParams($value);
        $main = trim((string) array_shift($parts));
        $params = [];
        foreach ($parts as $p) {
            if (! str_contains($p, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $p, 2);
            $params[strtolower(trim($k))] = trim(trim($v), '"');
        }

        return [$main, $params];
    }

    /** split on ; outside quotes */
    private function splitParams(string $v): array
    {
        $out = [];
        $buf = '';
        $q = false;
        for ($i = 0, $n = strlen($v); $i < $n; $i++) {
            $c = $v[$i];
            if ($c === '"') {
                $q = ! $q;
            }
            if ($c === ';' && ! $q) {
                $out[] = $buf;
                $buf = '';

                continue;
            }
            $buf .= $c;
        }
        $out[] = $buf;

        return $out;
    }

    private function paramName(array $params, string $key): ?string
    {
        if (isset($params[$key.'*'])) { // RFC 2231: utf-8''na%C3%AFve.pdf
            return $this->rfc2231($params[$key.'*']);
        }
        $cont = [];
        foreach ($params as $k => $v) {
            if (preg_match('/^'.preg_quote($key, '/').'\*(\d+)(\*?)$/', $k, $m)) {
                $cont[(int) $m[1]] = [$v, $m[2] === '*'];
            }
        }
        if ($cont) {
            ksort($cont);
            $encoded = $cont[0][1];
            $joined = implode('', array_map(fn ($c) => $c[0], $cont));

            return $encoded ? $this->rfc2231($joined) : $joined;
        }

        return isset($params[$key]) ? $this->decodeHeader($params[$key]) : null;
    }

    private function rfc2231(string $v): string
    {
        $bits = explode("'", $v, 3);
        if (count($bits) === 3) {
            return $this->toUtf8(rawurldecode($bits[2]), $bits[0] ?: 'utf-8');
        }

        return rawurldecode($v);
    }

    private function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $body)),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    public function toUtf8(string $s, ?string $charset): string
    {
        $charset = strtolower(trim((string) $charset, " \"'"));
        if ($charset === '' || in_array($charset, ['utf-8', 'utf8', 'us-ascii', 'ascii'], true)) {
            return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        $charset = ['ks_c_5601-1987' => 'CP949', 'x-user-defined' => 'Windows-1252', 'iso-8859-8-i' => 'ISO-8859-8'][$charset] ?? $charset;
        try {
            $out = mb_convert_encoding($s, 'UTF-8', $charset);
        } catch (\ValueError) {
            $out = function_exists('iconv') ? (@iconv($charset, 'UTF-8//IGNORE', $s) ?: '') : '';
            if ($out === '') {
                $out = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
            }
        }

        return $out;
    }

    /** RFC 2047 encoded words → UTF-8 (adjacent words joined, as the RFC requires). */
    public function decodeHeader(string $v): string
    {
        $v = preg_replace('/(=\?[^?]+\?[BbQq]\?[^?]*\?=)\s+(?==\?[^?]+\?[BbQq]\?[^?]*\?=)/', '$1', $v);
        $decoded = preg_replace_callback('/=\?([^?*]+)(?:\*[^?]+)?\?([BbQq])\?([^?]*)\?=/', function ($m) {
            $bytes = strtoupper($m[2]) === 'B'
                ? (string) base64_decode($m[3])
                : quoted_printable_decode(str_replace('_', ' ', $m[3]));

            return $this->toUtf8($bytes, $m[1]);
        }, $v);

        return trim($this->toUtf8((string) $decoded, null));
    }

    /** @return list<array{email:string, name:?string}> */
    public function addresses(string $v): array
    {
        $v = $this->decodeHeader($v);
        $out = [];
        $buf = '';
        $q = false;
        $angle = false;
        $list = [];
        for ($i = 0, $n = strlen($v); $i < $n; $i++) {
            $c = $v[$i];
            $q = $c === '"' ? ! $q : $q;
            $angle = $c === '<' ? true : ($c === '>' ? false : $angle);
            if (($c === ',' || $c === ';') && ! $q && ! $angle) {
                $list[] = $buf;
                $buf = '';

                continue;
            }
            $buf .= $c;
        }
        $list[] = $buf;
        foreach ($list as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (preg_match('/^(.*)<([^>]+)>\s*$/s', $item, $m)) {
                $email = strtolower(trim($m[2]));
                $name = trim(trim($m[1]), "\"' ") ?: null;
            } else {
                $email = strtolower(trim($item, '<> '));
                $name = null;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = ['email' => $email, 'name' => $name];
            }
        }

        return $out;
    }

    /** "<a@b> <c@d>" → ["a@b", "c@d"] (lower-cased, brackets removed) */
    public function ids(string $v): array
    {
        preg_match_all('/<([^<>\s]+)>/', $v, $m);
        $ids = $m[1] ?: (trim($v) !== '' ? [trim($v)] : []);

        return array_values(array_unique(array_map(fn ($id) => strtolower(trim($id, '<> ')), $ids)));
    }

    /** Plain text for previews: HTML stripped, whitespace collapsed. */
    public function plain(?string $text, ?string $html): string
    {
        if ($text !== null && trim($text) !== '') {
            return $text;
        }
        if ($html === null) {
            return '';
        }
        $h = preg_replace('#<(script|style|head)[^>]*>.*?</\1>#is', '', $html);
        $h = preg_replace('#<br\s*/?>|</(p|div|tr|li|h\d)>#i', "\n", (string) $h);

        return trim(html_entity_decode(strip_tags((string) $h), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Split a reply into what the person wrote and the quoted history below it.
     *
     * @return array{0:string, 1:string}
     */
    public function splitQuote(string $text): array
    {
        $lines = preg_split("/\r?\n/", $text) ?: [];
        foreach ($lines as $i => $line) {
            $t = trim($line);
            $marker = preg_match('/^(On\s.+wrote:|-{2,}\s*Original Message\s*-{2,}|From:\s.+|_{10,}|.*লিখেছেন:)$/i', $t)
                || (str_starts_with($t, '>') && $i > 0 && collect(array_slice($lines, $i))->filter(fn ($l) => trim($l) !== '')->every(fn ($l) => str_starts_with(trim($l), '>')));
            if ($marker && $i > 0) {
                return [rtrim(implode("\n", array_slice($lines, 0, $i))), implode("\n", array_slice($lines, $i))];
            }
        }

        return [rtrim($text), ''];
    }

    private function safeName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1f\/\\\\:*?"<>|]+/', '_', $name), ' .');

        return mb_substr($name !== '' ? $name : 'attachment', 0, 180);
    }

    private function ext(string $mime): string
    {
        return ['image/png' => '.png', 'image/jpeg' => '.jpg', 'image/gif' => '.gif', 'application/pdf' => '.pdf', 'text/calendar' => '.ics'][$mime] ?? '';
    }
}
