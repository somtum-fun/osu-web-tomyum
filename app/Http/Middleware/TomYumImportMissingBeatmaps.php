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

    public function handle(Request $request, Closure $next)
    {
        if (present($GLOBALS['cfg']['osu']['legacy']['shared_interop_secret'])) {
            try {
                $this->importMissing($request);
            } catch (\Throwable $e) {
                // Never break the request over it: it just 404s like before.
                log_error($e);
            }
        }

        return $next($request);
    }

    private function importMissing(Request $request): void
    {
        $path = preg_replace('#^api/v2/#', '', trim($request->path(), '/'));
        $beatmapIds = [];
        $setIds = [];
        $checksums = [];

        if ($path === 'beatmapsets/lookup') {
            $beatmapIds[] = $request->query('beatmap_id');
        } elseif (preg_match('#^beatmapsets/([0-9]+)(/|$)#', $path, $match) === 1) {
            $setIds[] = $match[1];
        } elseif ($path === 'beatmaps/lookup') {
            $beatmapIds[] = $request->query('id');
            $checksums[] = $request->query('checksum');
        } elseif ($path === 'beatmaps') {
            $beatmapIds = array_slice((array) $request->query('ids', []), 0, 50);
        } elseif (preg_match('#^beatmaps/([0-9]+)(/|$)#', $path, $match) === 1) {
            $beatmapIds[] = $match[1];
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
                $this->import('set', (string) $setId, fn (TomYumAdmin $admin) => $admin->post("/beatmapsets/{$setId}/import"));
            }
        }
        foreach (array_unique(array_filter(array_map('get_int', $beatmapIds))) as $beatmapId) {
            if ($imports < static::MAX_IMPORTS && !Beatmap::whereKey($beatmapId)->exists()) {
                $imports++;
                $this->import('beatmap', (string) $beatmapId, fn (TomYumAdmin $admin) => $admin->post('/beatmaps/import', ['beatmapId' => $beatmapId]));
            }
        }
        foreach (array_unique(array_filter($checksums, fn ($c) => is_string($c) && preg_match('/^[0-9a-f]{32}$/i', $c) === 1)) as $checksum) {
            if ($imports < static::MAX_IMPORTS && !Beatmap::where('checksum', $checksum)->exists()) {
                $imports++;
                $this->import('checksum', strtolower($checksum), fn (TomYumAdmin $admin) => $admin->post('/beatmaps/import', ['checksum' => $checksum]));
            }
        }
    }

    private function import(string $type, string $value, callable $send): void
    {
        $key = "tomyum:unknown-beatmap:{$type}:{$value}";
        if (Cache::has($key)) {
            return;
        }

        $response = $send(new TomYumAdmin(auth()->user()));
        if ($response->status() === 404) {
            Cache::put($key, true, static::UNKNOWN_TTL);
        }
    }
}
