<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\InvariantException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for TomYum's admin API (/_admin/*), used by the staff panels.
 *
 * Every request is signed like TomYum's AdminSignature expects: HMAC-SHA256 (keyed with the
 * shared InterOp secret) over "METHOD\npath?query\nactorId\nbody", with a unix `timestamp` in the
 * query. Permission checks happen in the panel controllers; TomYum trusts signed requests.
 */
class TomYumAdmin
{
    public function __construct(private ?User $actor = null)
    {
    }

    /** The replay data of website score $scoreId from TomYum, or null when it has none / can't be reached. */
    public static function replayFor(int $scoreId): ?string
    {
        try {
            $response = (new static())->get("/replays/website-score/{$scoreId}");
        } catch (InvariantException $e) {
            log_error($e);

            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $path, $query);
    }

    public function post(string $path, ?array $body = null, array $query = []): Response
    {
        return $this->send('POST', $path, $query, $body);
    }

    public function delete(string $path, array $query = []): Response
    {
        return $this->send('DELETE', $path, $query);
    }

    private function send(string $method, string $path, array $query, ?array $body = null): Response
    {
        $secret = $GLOBALS['cfg']['osu']['legacy']['shared_interop_secret'];
        if (!present($secret)) {
            throw new InvariantException('TomYum admin API is not configured (SHARED_INTEROP_SECRET).');
        }

        $query['timestamp'] = time();
        $pathAndQuery = '/_admin'.$path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $json = $body === null ? '' : json_encode($body);
        $actorId = (string) ($this->actor?->getKey() ?? '');
        $signature = hash_hmac('sha256', "{$method}\n{$pathAndQuery}\n{$actorId}\n{$json}", $secret);

        $request = Http::timeout(30)
            ->acceptJson()
            ->withHeaders([
                'X-Actor-Id' => $actorId,
                'X-TomYum-Signature' => $signature,
            ]);
        if ($body !== null) {
            $request = $request->withBody($json, 'application/json');
        }

        try {
            return $request->send($method, rtrim($GLOBALS['cfg']['tomyum']['admin_url'], '/').$pathAndQuery);
        } catch (ConnectionException $e) {
            throw new InvariantException('Could not reach the game server: '.$e->getMessage());
        }
    }
}
