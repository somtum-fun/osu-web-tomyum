<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Build;
use Carbon\CarbonInterface;
use DB;

/**
 * TomYum: this server's changelog streams (osu_updates.streams), shared by the migration that makes
 * them, the git importer (tomyum:changelog-import) and the changelog panel. Which ones the page shows,
 * and in what order, is UPDATE_STREAMS; their colours are in resources/css (changelog-stream--name).
 */
class TomYumChangelog
{
    /** name => [id, name on the page]. */
    public const STREAMS = [
        'lazer' => [6, 'lazer-fork'],
        'stable40' => [5, 'Stable'],
        'legacy' => [13, 'Legacy clients'],
        'server' => [10, 'Server'],
        'web' => [1, 'Website'],
        'discord' => [11, 'Discord'],
        'wiki' => [12, 'Wiki'],
    ];

    /** Makes the streams that aren't there yet (names given on the panel are kept). */
    public static function ensureStreams(): void
    {
        $rows = [];
        foreach (static::STREAMS as $name => [$id, $pretty]) {
            $rows[] = ['stream_id' => $id, 'name' => $name, 'pretty_name' => $pretty, 'default_allow_bancho' => false];
        }
        DB::connection('mysql-updates')->table('streams')->insertOrIgnore($rows);
    }

    public static function streamIds(): array
    {
        return array_column(static::STREAMS, 0);
    }

    /** The next version of a stream on a day, like the importer numbers them: 2026.1006.0, 2026.1006.1, … */
    public static function nextVersion(int $streamId, CarbonInterface $at): string
    {
        $day = $at->format('Y').'.'.$at->format('n').$at->format('d');
        $next = 0;
        foreach (Build::where('stream_id', $streamId)->where('version', 'like', "{$day}.%")->pluck('version') as $version) {
            $number = substr($version, strlen($day) + 1);
            if (ctype_digit($number)) {
                $next = max($next, (int) $number + 1);
            }
        }

        return "{$day}.{$next}";
    }
}
