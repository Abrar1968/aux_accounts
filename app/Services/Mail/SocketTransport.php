<?php

namespace App\Services\Mail;

use RuntimeException;

/** Implicit-TLS (imaps, port 993) or plain socket to the mail server. */
class SocketTransport implements ImapTransport
{
    /** @var resource */
    private $stream;

    public function __construct(string $host, int $port = 993, string $encryption = 'ssl', bool $verifyPeer = true, int $timeout = 20)
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $verifyPeer, 'verify_peer_name' => $verifyPeer, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';
        $stream = @stream_socket_client("{$scheme}://{$host}:{$port}", $errno, $error, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (! $stream) {
            throw new RuntimeException("Cannot reach the mail server {$host}:{$port} ({$error}).");
        }
        stream_set_timeout($stream, $timeout);
        $this->stream = $stream;
    }

    public function write(string $data): void
    {
        if (@fwrite($this->stream, $data) === false) {
            throw new RuntimeException('Mail server connection lost while writing.');
        }
    }

    public function readLine(): string
    {
        $line = fgets($this->stream);
        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            if ($meta['timed_out'] ?? false) {
                throw new RuntimeException('Mail server did not answer in time.');
            }

            return '';
        }

        return $line;
    }

    public function read(int $bytes): string
    {
        $out = '';
        while (strlen($out) < $bytes) {
            $chunk = fread($this->stream, min(65536, $bytes - strlen($out)));
            if ($chunk === false || $chunk === '') {
                if (feof($this->stream) || (stream_get_meta_data($this->stream)['timed_out'] ?? false)) {
                    throw new RuntimeException('Mail server connection lost while reading a message.');
                }

                continue;
            }
            $out .= $chunk;
        }

        return $out;
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
