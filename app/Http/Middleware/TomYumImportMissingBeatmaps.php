<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Libraries\TomYumAdmin;
use App\Models\Beatmap;
use App\Models\Beatmapset;
use Cache;
use Closure;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

/**
 * TomYum: the site only has the beatmaps TomYum imported, but osu!(lazer) asks about any map the
 * player has. Before a request about a beatmap or set the site doesn't have, ask TomYum to import
 * it from bancho (its /_admin/beatmaps/import and /_admin/beatmapsets/{id}/import), so lazer's
 * leaderboards, score submission and multiplayer find it. Maps osu! doesn't know are remembered for
 * a while so they aren't looked up on every request. See TomYum's docs/lazer.md.
 */
class TomYumImportMissingBeatmaps
{
    /** Seconds before an unknown map is looked up again. */
    private const UNKNOWN_TTL = 3600;

    /** At most this many imports per request (a batch lookup can list many maps). */
    private const MAX_IMPORTS = 10;

    /**
     * At most this many imports per client IP per hour: lazer asks about every set a player looks at in
     * song select. TomYum looks maps up on a mirror first and keeps its osu! API use under osu!'s limit
     * for the whole server; this just stops one player from taking all of it.
     */
    private const IMPORTS_PER_HOUR = 120;

    /** Visitors who aren't signed in: only by opening a beatmap's or a set's page, and fewer an hour. */
    private const ANONYMOUS_IMPORTS_PER_HOUR = 30;

    public function handle(Request $request, Closure $next)
    {
        // Signed-in clients (lazer always sends a token; site visitors have a session) for anything that
        // names a map. Anyone else only by opening a beatmap's or a set's page (a link from osu.ppy.sh,
        // /b/123 and /s/123), with a lower hourly limit, so anonymous traffic can't make the server
        // fetch maps in bulk.
        $signedIn = auth()->check();
        $allowed = $signedIn || ($request->isMethod('GET') && static::isPagePath($request));
        if ($allowed && present($GLOBALS['cfg']['osu']['legacy']['shared_interop_secret'])) {
            try {
                $this->importMissing($request);
            } catch (\Throwable $e) {
                // Never break the request over it: it just 404s like before.
                log_error($e);
            }
        }

        return $next($request);
    }

    /** A beatmap's or a set's page on the website (not the API). */
    private static function isPagePath(Request $request): bool
    {
        return preg_match('#^(beatmapsets|beatmaps|b|s)/[0-9]+$#', trim($request->path(), '/')) === 1;
    }

    private function importMissing(Request $request): void
    {
        $path = preg_replace('#^api/v2/#', '', trim($request->path(), '/'));
        $beatmapIds = [];
        $setIds = [];
        $checksums = [];

        if ($path === 'beatmapsets/lookup') {
            $beatmapIds[] = $request->query('beatmap_id');
        } elseif (preg_match('#^(beatmapsets|s)/([0-9]+)(/|$)#', $path, $match) === 1) {
            $setIds[] = $match[2];
        } elseif ($path === 'beatmaps/lookup') {
            $beatmapIds[] = $request->query('id');
            $checksums[] = $request->query('checksum');
        } elseif ($path === 'beatmaps') {
            $beatmapIds = array_slice((array) $request->query('ids', []), 0, 50);
        } elseif (preg_match('#^(beatmaps|b)/([0-9]+)(/|$)#', $path, $match) === 1) {
            $beatmapIds[] = $match[2];
        } elseif ($request->isMethod('POST') && preg_match('#^(_lio/multiplayer/rooms|rooms)$#', $path) === 1) {
            foreach ((array) $request->input('playlist', []) as $item) {
                $beatmapIds[] = is_array($item) ? ($item['beatmap_id'] ?? null) : null;
            }
        } elseif ($request->isMethod('POST') && preg_match('#^rooms/[0-9]+/playlist$#', $path) === 1) {
            $beatmapIds[] = $request->input('beatmap_id');
        }

        $imports = 0;
        foreach (array_unique(array_filter(array_map('get_int', $setIds))) as $setId) {
            if ($imports < static::MAX_IMPORTS && !Beatmapset::whereKey($setId)->exists()) {
                $imports++;
                $this->import('set', (string) $setId, fn (TomYumAdmin $admin, array $via) => $admin->post("/beatmapsets/{$setId}/import", null, $via));
            }
        }
        foreach (array_unique(array_filter(array_map('get_int', $beatmapIds))) as $beatmapId) {
            if ($imports < static::MAX_IMPORTS && !Beatmap::whereKey($beatmapId)->exists()) {
                $imports++;
                $this->import('beatmap', (string) $beatmapId, fn (TomYumAdmin $admin, array $via) => $admin->post('/beatmaps/import', ['beatmapId' => $beatmapId], $via));
            }
        }
        foreach (array_unique(array_filter($checksums, fn ($c) => is_string($c) && preg_match('/^[0-9a-f]{32}$/i', $c) === 1)) as $checksum) {
            if ($imports < static::MAX_IMPORTS && !Beatmap::where('checksum', $checksum)->exists()) {
                $imports++;
                $this->import('checksum', strtolower($checksum), fn (TomYumAdmin $admin, array $via) => $admin->post('/beatmaps/import', ['checksum' => $checksum], $via));
            }
        }
    }

    private function import(string $type, string $value, callable $send): void
    {
        $key = "tomyum:unknown-beatmap:{$type}:{$value}";
        if (Cache::has($key)) {
            return;
        }

        $ip = request()->ip() ?? 'unknown';
        $limit = auth()->check() ? static::IMPORTS_PER_HOUR : static::ANONYMOUS_IMPORTS_PER_HOUR;
        if (RateLimiter::tooManyAttempts("tomyum-import:{$ip}", $limit)) {
            return;
        }
        RateLimiter::hit("tomyum-import:{$ip}", 3600);

        // The import log shows who asked, and from which client.
        $via = ['via' => request()->bearerToken() !== null ? 'lazer' : 'website'];
        $response = $send(new TomYumAdmin(auth()->user()), $via);
        if ($response->status() === 404) {
            Cache::put($key, true, static::UNKNOWN_TTL);
        }
    }
}
