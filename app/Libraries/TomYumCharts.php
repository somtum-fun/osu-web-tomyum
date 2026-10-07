<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: the admin panel overview's charts, as lists of [label, value, tooltip] for
 * panel/_chart.blade.php. Days are UTC.
 */
class TomYumCharts
{
    /** Players in the game (stable and lazer) over the last 24 hours, each 5-minute sample (tomyum:online-sample). */
    public static function onlineToday(): array
    {
        if (!Schema::hasTable('tomyum_online_samples')) {
            return [];
        }

        return DB::table('tomyum_online_samples')
            ->where('sampled_at', '>=', Carbon::now()->subDay())
            ->orderBy('sampled_at')
            ->get()
            ->map(function ($sample) {
                $at = Carbon::parse($sample->sampled_at, 'UTC');
                $inGame = (int) $sample->stable + (int) $sample->lazer;

                return [
                    $at->format('H:i'),
                    $inGame,
                    "{$at->format('H:i')} UTC: {$inGame} in game ({$sample->stable} stable, {$sample->lazer} lazer), {$sample->website} on the website",
                ];
            })
            ->all();
    }

    /** Scores submitted each day (stable and lazer, passed or not), the last $days days. */
    public static function scoresPerDay(int $days = 14): array
    {
        $from = CarbonImmutable::now('UTC')->startOfDay()->subDays($days - 1);
        $counts = DB::table('scores')
            ->where('ended_at', '>=', $from)
            ->selectRaw('DATE(ended_at) AS day, COUNT(*) AS n')
            ->groupBy('day')
            ->pluck('n', 'day');

        return static::days($from, $days, $counts, 'score', 'scores');
    }

    /** New accounts each day (bots left out), the last $days days. */
    public static function signupsPerDay(int $days = 14): array
    {
        $from = CarbonImmutable::now('UTC')->startOfDay()->subDays($days - 1);
        $counts = DB::table('phpbb_users')
            ->where('user_regdate', '>=', $from->getTimestamp())
            ->where('group_id', '<>', app('groups')->byIdentifier('bot')->getKey())
            ->selectRaw('DATE(FROM_UNIXTIME(user_regdate)) AS day, COUNT(*) AS n')
            ->groupBy('day')
            ->pluck('n', 'day');

        return static::days($from, $days, $counts, 'sign-up', 'sign-ups');
    }

    /** One point per day, days without any as 0. */
    private static function days(CarbonImmutable $from, int $days, $counts, string $one, string $many): array
    {
        $points = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->addDays($i);
            $value = (int) ($counts[$day->toDateString()] ?? 0);
            $points[] = [$day->format('j M'), $value, $day->format('D j M').': '.number_format($value).' '.($value === 1 ? $one : $many)];
        }

        return $points;
    }
}
