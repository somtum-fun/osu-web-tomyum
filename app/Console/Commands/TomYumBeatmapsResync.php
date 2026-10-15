<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumBeatmapSync;
use Illuminate\Console\Command;

/**
 * TomYum: reads osu! beatmap sets again from bancho, a few at a time (App\Libraries\TomYumBeatmapSync):
 * sets with missing details (key count, genre, language, …) first, then sets whose status on osu!
 * may still change.
 */
class TomYumBeatmapsResync extends Command
{
    protected $signature = 'tomyum:beatmaps-resync {--limit=40 : Sets per run} {--seconds=480 : Stop starting new ones after this long} {--dry-run : Only list the sets due}';

    protected $description = 'Syncs beatmap sets with bancho: missing details first, then statuses that may change.';

    public function handle()
    {
        $ids = TomYumBeatmapSync::due((int) $this->option('limit'));
        if ($this->option('dry-run')) {
            $this->line(count($ids).' set(s) due: '.implode(' ', $ids));

            return;
        }

        $start = microtime(true);
        $counts = ['synced' => 0, 'not_found' => 0, 'failed' => 0];
        foreach ($ids as $id) {
            if (microtime(true) - $start > (int) $this->option('seconds')) {
                break;
            }
            $counts[TomYumBeatmapSync::sync($id, null, 'schedule')]++;
        }

        if (array_sum($counts) > 0) {
            $this->info("Synced {$counts['synced']}, not on osu! {$counts['not_found']}, failed {$counts['failed']}.");
        }
    }
}
