<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Beatmap;
use App\Models\Country;
use App\Models\CountryStatistics;
use App\Models\UserStatistics;

/**
 * Country ranking data for a TomYum server. On osu! the game server keeps osu_countries' totals
 * up to date and a scheduled command builds the country rankings from them; here TomYum asks for a
 * country to be recalculated whenever a player's stats change.
 */
class TomYumCountryStats
{
    public static function recalculate(string $acronym): void
    {
        $acronym = strtoupper($acronym);
        if (!Country::where('acronym', $acronym)->exists()) {
            return;
        }

        // osu_countries' totals: what rankings:recalculate-country-stats uses to pick countries.
        $totals = ['rankedscore' => 0, 'playcount' => 0, 'usercount' => 0, 'pp' => 0];
        foreach (Beatmap::MODES as $modeStr => $_modeInt) {
            $row = UserStatistics\Model::getClass($modeStr)
                ::where('country_acronym', $acronym)
                ->where('rank_score', '>', 0)
                ->whereHas('user', fn ($query) => $query->default())
                ->selectRaw('coalesce(sum(ranked_score), 0) as ranked_score, coalesce(sum(playcount), 0) as playcount, count(*) as users, coalesce(sum(rank_score), 0) as pp')
                ->toBase()
                ->first();

            $totals['rankedscore'] += (int) $row->ranked_score;
            $totals['playcount'] += (int) $row->playcount;
            $totals['usercount'] = max($totals['usercount'], (int) $row->users);
            $totals['pp'] += (int) $row->pp;
        }
        Country::where('acronym', $acronym)->update($totals);

        foreach (Beatmap::MODES as $modeInt) {
            CountryStatistics::recalculate($acronym, $modeInt);
        }
    }
}
