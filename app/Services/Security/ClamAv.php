<?php

namespace App\Services\Security;

use RuntimeException;

/**
 * A minimal clamd client: PING and INSTREAM over a unix or TCP socket.
 * No package, no exec - the file is streamed to the daemon, so clamd does not
 * need read access to PHP's upload directory.
 *
 * Address: config('atompay.kyc.scan.clamav'), e.g.
 *   unix:///var/run/clamav/clamd.ctl   (Debian/Ubuntu clamav-daemon)
 *   tcp://127.0.0.1:3310
 */
class ClamAv
{
    /** clamd's default StreamMaxLength is 25 MB; KYC uploads are capped far below. */
    private const CHUNK = 8192;

    public function configured(): bool
    {
        return filled($this->address());
    }

    public function ping(): bool
    {
        try {
            $socket = $this->connect();
            fwrite($socket, "zPING\0");
            $reply = $this->readReply($socket);
            fclose($socket);

            return $reply === 'PONG';
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @return string|null the signature name when infected, null when clean
     * @throws RuntimeException when clamd cannot be reached or answers with an error
     */
    public function scan(string $path): ?string
    {
        $file = @fopen($path, 'rb');
        if ($file === false) {
            throw new RuntimeException('Cannot open the file to scan.');
        }

        $socket = $this->connect();

        try {
            fwrite($socket, "zINSTREAM\0");
            while (! feof($file)) {
                $chunk = fread($file, self::CHUNK);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                if (fwrite($socket, pack('N', strlen($chunk)).$chunk) === false) {
                    throw new RuntimeException('clamd closed the connection mid-scan.');
                }
            }
            fwrite($socket, pack('N', 0));

            $reply = $this->readReply($socket);
        } finally {
            fclose($file);
            fclose($socket);
        }

        // "stream: OK" | "stream: Eicar-Test-Signature FOUND" | "... ERROR"
        if (str_ends_with($reply, ' OK')) {
            return null;
        }
        if (str_ends_with($reply, ' FOUND')) {
            return trim(substr($reply, strpos($reply, ':') + 1, -strlen(' FOUND')));
        }

        throw new RuntimeException('clamd: '.$reply);
    }

    private function address(): ?string
    {
        return config('atompay.kyc.scan.clamav');
    }

    /** @return resource */
    private function connect()
    {
        $timeout = (int) config('atompay.kyc.scan.timeout', 30);
        $socket = @stream_socket_client((string) $this->address(), $errno, $error, $timeout);

        if ($socket === false) {
            throw new RuntimeException("Cannot reach clamd at {$this->address()}: {$error} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);

        return $socket;
    }

    /** @param resource $socket */
    private function readReply($socket): string
    {
        $reply = '';
        while (! feof($socket)) {
            $byte = fread($socket, 1);
            if ($byte === false || $byte === '' || $byte === "\0") {
                break;
            }
            $reply .= $byte;
        }

        if (stream_get_meta_data($socket)['timed_out'] ?? false) {
            throw new RuntimeException('clamd did not answer in time.');
        }
        if ($reply === '') {
            throw new RuntimeException('clamd sent an empty reply.');
        }

        return trim($reply);
    }
}
