<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: the little IMAP the ticket mailbox needs (TomYumTickets), over TLS: log in, open a folder,
 * find messages by UID and fetch them whole without marking them read. PHP has no IMAP extension
 * any more (it left the core in 8.4), so this speaks the protocol itself.
 */
class TomYumImap
{
    /** @var resource */
    private $stream;
    private int $tag = 0;

    public function __construct(string $host, int $port)
    {
        $context = stream_context_create(['ssl' => ['peer_name' => $host]]);
        $stream = @stream_socket_client("ssl://{$host}:{$port}", $errno, $error, 30, STREAM_CLIENT_CONNECT, $context);
        if ($stream === false) {
            throw new \RuntimeException("IMAP: can't connect to {$host}:{$port}: {$error}");
        }
        stream_set_timeout($stream, 60);
        $this->stream = $stream;

        $greeting = $this->readResponse();
        if (!str_starts_with($greeting, '* OK') && !str_starts_with($greeting, '* PREAUTH')) {
            throw new \RuntimeException('IMAP: unexpected greeting: '.trim($greeting));
        }
    }

    public function login(string $username, string $password): void
    {
        $this->command('LOGIN '.static::quote($username).' '.static::quote($password));
    }

    /** Opens a folder read-only; returns its UIDVALIDITY and UIDNEXT. */
    public function examine(string $folder): array
    {
        $box = ['uidvalidity' => 0, 'uidnext' => null];
        foreach ($this->command('EXAMINE '.static::quote($folder)) as $response) {
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $response, $match)) {
                $box['uidvalidity'] = (int) $match[1];
            }
            if (preg_match('/\[UIDNEXT (\d+)\]/i', $response, $match)) {
                $box['uidnext'] = (int) $match[1];
            }
        }
        $box['uidnext'] ??= max([0, ...$this->search('ALL')]) + 1;

        return $box;
    }

    /** UIDs matching a search (e.g. "UID 5:*"), ascending. */
    public function search(string $criteria): array
    {
        $uids = [];
        foreach ($this->command("UID SEARCH {$criteria}") as $response) {
            if (preg_match('/^\* SEARCH\b(.*)$/i', trim($response), $match)) {
                array_push($uids, ...array_map('intval', preg_split('/\s+/', trim($match[1]), -1, PREG_SPLIT_NO_EMPTY)));
            }
        }
        sort($uids);

        return $uids;
    }

    /** The whole message (raw RFC 822) and when the server got it, or null if it's gone. */
    public function fetch(int $uid): ?array
    {
        foreach ($this->command("UID FETCH {$uid} (INTERNALDATE BODY.PEEK[])") as $response) {
            if (!preg_match('/BODY\[\] \{(\d+)\}\r\n/', $response, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $start = $match[0][1] + strlen($match[0][0]);
            preg_match('/INTERNALDATE "([^"]+)"/', $response, $date);

            return [
                'raw' => substr($response, $start, (int) $match[1][0]),
                'date' => isset($date[1]) ? trim($date[1]) : null,
            ];
        }

        return null;
    }

    public function logout(): void
    {
        try {
            $this->command('LOGOUT');
        } catch (\Throwable) {
            // Already closed: nothing to say goodbye to.
        }
        fclose($this->stream);
    }

    /** Sends a command; returns its untagged responses (literals included), or throws on NO/BAD. */
    private function command(string $command): array
    {
        $tag = 'T'.(++$this->tag);
        fwrite($this->stream, "{$tag} {$command}\r\n");

        $responses = [];
        while (true) {
            $response = $this->readResponse();
            if (str_starts_with($response, "{$tag} ")) {
                if (!preg_match('/^\S+ OK\b/i', $response)) {
                    // Only the command's name: LOGIN's arguments are the password.
                    throw new \RuntimeException('IMAP '.strtok($command, ' ').' failed: '.trim(substr($response, strlen($tag) + 1)));
                }

                return $responses;
            }
            $responses[] = $response;
        }
    }

    /** One response: a line, with any literals ("{123}" and that many bytes) and what follows them. */
    private function readResponse(): string
    {
        $response = '';
        while (true) {
            $line = fgets($this->stream);
            if ($line === false) {
                throw new \RuntimeException('IMAP: the connection closed'.(stream_get_meta_data($this->stream)['timed_out'] ? ' (timed out)' : ''));
            }
            $response .= $line;
            if (!preg_match('/\{(\d+)\}\r\n$/', $line, $match)) {
                return $response;
            }
            $response .= $this->read((int) $match[1]);
        }
    }

    private function read(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->stream, $length - strlen($data));
            if ($chunk === false || ($chunk === '' && feof($this->stream))) {
                throw new \RuntimeException('IMAP: the connection closed mid-message');
            }
            if ($chunk === '' && stream_get_meta_data($this->stream)['timed_out']) {
                throw new \RuntimeException('IMAP: timed out mid-message');
            }
            $data .= $chunk;
        }

        return $data;
    }

    private static function quote(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }
}
