<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Models\Traits;

use App\Libraries\Score\FetchDedupedScores;
use App\Libraries\Search\ScoreSearchParams;
use App\Models\Beatmap;
use App\Models\Solo\Score;
use Illuminate\Database\Eloquent\Collection;

trait UserScoreable
{
    private array $beatmapBestScoreIds = [];

    public function aggregatedScoresBest(string $mode, int $size): array
    {
        return (new FetchDedupedScores('beatmap_id', ScoreSearchParams::fromArray([
            // TomYum: Relax and Autopilot plays have their own top plays (the profile's RX/AP modes); a
            // lazer one shows its Akatsuki pp on its own page but isn't a vanilla top play.
            'exclude_mods' => ScoreSearchParams::RELAX_MODS,
            'exclude_without_pp' => true,
            'limit' => $size,
            'ruleset_id' => Beatmap::MODES[$mode],
            'sort' => 'pp_desc',
            'user_id' => $this->getKey(),
        ]), "aggregatedScoresBest_{$mode}"))->all(['beatmap_id', 'id']);
    }

    public function beatmapBestScoreIds(string $mode)
    {
        // aggregations do not support regular pagination.
        // always fetching 200 to cache; we're not supporting beyond 200, either.
        return $this->beatmapBestScoreIds[$mode] ??= cache_remember_mutexed(
            "search-cache:beatmapBestScoresSolo-v2-tomyum1:{$this->getKey()}:{$mode}",
            $GLOBALS['cfg']['osu']['scores']['es_cache_duration'],
            [],
            fn () => array_column($this->aggregatedScoresBest($mode, 200), 'id'),
            function () {
                // TODO: propagate a more useful message back to the client
                // for now we just mark the exception as handled.
                return true;
            }
        );
    }

    public function beatmapBestScores(string $mode, int $limit, int $offset, array $with): Collection
    {
        $ids = array_slice($this->beatmapBestScoreIds($mode), $offset, $limit);
        $results = Score::whereKey($ids)->orderByField('id', $ids)->default()->get();

        $results->load($with);
        // make outdated index less obvious
        $results = $results->sortByDesc('pp');

        // fill in positions for weighting
        // also preload the user relation
        $position = $offset;
        foreach ($results as $result) {
            $result->weight = pow(0.95, $position);
            $result->setRelation('user', $this);
            $position++;
        }

        return $results;
    }
}
