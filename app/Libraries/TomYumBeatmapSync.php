<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\User;
use Carbon\Carbon;
use DB;

/**
 * TomYum: keeps osu!'s beatmap sets in step with bancho. TomYum imports a set with all its details
 * (key count, OD/HP/AR, BPM, object counts, genre, language, tags, status) when it's first asked
 * for, but sets that came over from bancho.py (or before TomYum kept those details) only have the
 * basics: osu!mania maps show as 0K/1K, genre and language as "unspecified". And a set's status on
 * osu! moves on (pending → qualified → ranked) after it's imported.
 *
 * So a set is imported again (TomYum's /_admin/beatmapsets/{id}/import, which keeps statuses staff
 * set here) when someone opens one with missing details (TomYumImportMissingBeatmaps), every few
 * minutes in the background (tomyum:beatmaps-resync: missing details first, then sets whose osu!
 * status may still change), and when staff press "Sync from osu!" on the set's page.
 */
class TomYumBeatmapSync
{
    /** How often a set is read again, by its status here (osu-web `approved`). Ranked/approved/loved: not. */
    const INTERVALS = [
        3 => 6 * 3600,          // qualified: may rank or be disqualified any day
        0 => 3 * 86400,         // pending
        -1 => 3 * 86400,        // WIP
        -2 => 30 * 86400,       // graveyard
    ];

    /** A set whose details are still missing isn't tried again sooner than this (osu! may not have them either). */
    const INCOMPLETE_RETRY = 86400;

    /** Whether the set (an osu! one) has difficulties with the details missing. */
    public static function isIncomplete(int $setId): bool
    {
        return $setId > 0 && $setId < 10_000_000
            && DB::table('osu_beatmaps')
                ->where('beatmapset_id', $setId)
                ->whereNull('deleted_at')
                ->where('diff_size', 0)
                ->where('countTotal', 0)
                ->exists();
    }

    /** Whether it was synced (or tried) within $seconds. */
    public static function syncedWithin(int $setId, int $seconds): bool
    {
        return DB::table('tomyum_beatmapset_syncs')
            ->where('beatmapset_id', $setId)
            ->where('synced_at', '>', Carbon::now()->subSeconds($seconds))
            ->exists();
    }

    /**
     * Imports the set again from bancho; 'synced', 'not_found' or 'failed'. $via is who asked (the
     * import log shows it): website, lazer, schedule.
     */
    public static function sync(int $setId, ?User $actor = null, string $via = 'website'): string
    {
        try {
            $response = (new TomYumAdmin($actor))->post("/beatmapsets/{$setId}/import", null, ['via' => $via]);
            $result = $response->successful() ? 'synced' : ($response->notFound() ? 'not_found' : 'failed');
        } catch (\Throwable $e) {
            log_error($e);
            $result = 'failed';
        }

        static::record($setId, $result);

        return $result;
    }

    /** Notes that the set was synced (or tried) now. */
    public static function record(int $setId, string $result): void
    {
        DB::table('tomyum_beatmapset_syncs')->upsert(
            [['beatmapset_id' => $setId, 'synced_at' => Carbon::now(), 'result' => $result]],
            ['beatmapset_id'],
            ['synced_at', 'result'],
        );
    }

    /**
     * The sets due a sync, at most $limit: those with missing details (not tried in the last day),
     * then those whose status may change on osu!, longest-waiting first.
     */
    public static function due(int $limit): array
    {
        $now = Carbon::now();
        $incomplete = DB::table('osu_beatmaps AS b')
            ->leftJoin('tomyum_beatmapset_syncs AS s', 's.beatmapset_id', '=', 'b.beatmapset_id')
            ->where('b.beatmapset_id', '>', 0)
            ->where('b.beatmapset_id', '<', 10_000_000)
            ->whereNull('b.deleted_at')
            ->where('b.diff_size', 0)
            ->where('b.countTotal', 0)
            ->where(fn ($q) => $q->whereNull('s.synced_at')->orWhere('s.synced_at', '<', $now->copy()->subSeconds(static::INCOMPLETE_RETRY)))
            ->distinct()
            ->orderBy('b.beatmapset_id', 'desc')
            ->limit($limit)
            ->pluck('b.beatmapset_id')
            ->all();

        $ids = array_map('intval', $incomplete);
        foreach (static::INTERVALS as $approved => $seconds) {
            if (count($ids) >= $limit) {
                break;
            }
            $more = DB::table('osu_beatmapsets AS bs')
                ->leftJoin('tomyum_beatmapset_syncs AS s', 's.beatmapset_id', '=', 'bs.beatmapset_id')
                ->where('bs.beatmapset_id', '>', 0)
                ->where('bs.beatmapset_id', '<', 10_000_000)
                ->whereNull('bs.deleted_at')
                ->where('bs.approved', $approved)
                ->where(fn ($q) => $q->whereNull('s.synced_at')->orWhere('s.synced_at', '<', $now->copy()->subSeconds($seconds)))
                ->whereNotIn('bs.beatmapset_id', $ids === [] ? [0] : $ids)
                ->orderByRaw('s.synced_at IS NOT NULL, s.synced_at')
                ->limit($limit - count($ids))
                ->pluck('bs.beatmapset_id')
                ->all();
            array_push($ids, ...array_map('intval', $more));
        }

        return $ids;
    }
}
