<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Libraries;

/**
 * TomYum: a rank graph's data (daily ranks, oldest first, 0 on a day without one) as the profile charts can draw it.
 * The website's chart and lazer's need two ranked days for a line, so a player with only one (new to a mode, or a
 * custom ruleset the day its ranks start) saw "No recent plays"; that day is repeated on the day next to it, which
 * draws a flat line at their rank.
 */
class TomYumRankHistory
{
    /**
     * @param int[] $data
     * @return int[]
     */
    public static function drawable(array $data): array
    {
        $ranked = array_keys(array_filter($data, fn ($rank) => (int) $rank !== 0));
        if (count($ranked) !== 1 || count($data) < 2) {
            return $data;
        }

        $day = $ranked[0];
        $data[$day > 0 ? $day - 1 : $day + 1] = $data[$day];

        return $data;
    }
}
