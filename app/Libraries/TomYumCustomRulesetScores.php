<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Beatmap;
use App\Models\Solo\Score;
use App\Models\TomYumCustomRuleset;
use App\Models\User;
use App\Transformers\ScoreTransformer;
use Cache;
use DB;

/**
 * TomYum: custom lazer rulesets' leaderboards and rankings (TomYumCustomRulesets), from the website's own scores, by
 * score: a map's board has each player's best (passed, ranked map, no automation mods), and the rankings add up
 * everyone's best on each ranked map (ranked score). Restricted players aren't on them.
 */
class TomYumCustomRulesetScores
{
    const BOARD_SIZE = 50;

    const RANKINGS_PAGE = 50;

    /**
     * A map's board for lazer and the beatmap page, shaped like the website's own (scores, user_score).
     *
     * @param string[] $mods Acronyms: only plays with exactly these mods ('NM': none); empty: all.
     */
    public static function beatmapLeaderboard(Beatmap $beatmap, TomYumCustomRuleset $ruleset, string $type, array $mods, array $includes): array
    {
        if ($beatmap->approved <= 0 || (int) $beatmap->playmode !== Beatmap::MODES['osu']) {
            return ['scores' => []];
        }

        $viewer = auth()->user();
        $userIds = match ($type) {
            'country' => $viewer === null ? [] : User::default()->where('country_acronym', $viewer->country_acronym)->pluck('user_id')->all(),
            'friend' => $viewer === null ? [] : [...$viewer->friends()->pluck('zebra_id'), $viewer->getKey()],
            'team' => $viewer?->team?->members()->pluck('user_id')->all() ?? [],
            default => null,
        };
        if ($userIds === []) {
            return ['scores' => []];
        }

        $best = static::bestPerUser($beatmap->getKey(), $ruleset->id, $userIds, $mods);
        $top = array_slice($best, 0, static::BOARD_SIZE);
        $scores = Score::with(['beatmap', 'user.country', 'user.team'])->whereIn('id', array_column($top, 'id'))->get()->keyBy('id');
        $transformer = new ScoreTransformer(false);

        $result = [
            'scores' => array_values(array_filter(array_map(
                fn ($row) => isset($scores[$row['id']]) ? json_item($scores[$row['id']], $transformer, $includes) : null,
                $top,
            ))),
            'score_count' => count($best),
        ];

        if ($viewer !== null) {
            foreach ($best as $position => $row) {
                if ($row['user_id'] === $viewer->getKey()) {
                    $mine = $scores[$row['id']] ?? Score::with(['beatmap', 'user.country', 'user.team'])->find($row['id']);
                    if ($mine !== null) {
                        $result['user_score'] = ['position' => $position + 1, 'score' => json_item($mine, $transformer, $includes)];
                    }
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Everyone's ranked score in a custom ruleset (their best on each ranked map, added up), highest first, kept five
     * minutes: [['user_id', 'ranked_score', 'maps', 'best'], ...] for one page, and how many players there are.
     *
     * @return array{rows: array<int, array{user_id: int, ranked_score: int, maps: int, best: int}>, total: int}
     */
    public static function rankings(TomYumCustomRuleset $ruleset, ?string $country, int $page): array
    {
        $all = Cache::remember("tomyum:custom-ruleset-rankings:{$ruleset->id}:".($country ?? ''), 300, function () use ($ruleset, $country) {
            $rows = DB::select(
                'SELECT b.user_id, CAST(SUM(b.best) AS SIGNED) AS ranked_score, COUNT(*) AS maps, CAST(MAX(b.best) AS SIGNED) AS best
                FROM (
                  SELECT s.user_id, s.beatmap_id, MAX(s.total_score) AS best
                  FROM scores s
                  WHERE s.ruleset_id = ? AND s.passed = 1 AND s.preserve = 1 AND s.ranked = 1
                  GROUP BY s.user_id, s.beatmap_id
                ) b
                JOIN phpbb_users u ON u.user_id = b.user_id AND u.user_warnings = 0 AND u.user_type = 0'.($country !== null ? ' AND u.country_acronym = ?' : '').'
                GROUP BY b.user_id
                ORDER BY ranked_score DESC, b.user_id
                LIMIT 10000',
                $country !== null ? [$ruleset->id, $country] : [$ruleset->id],
            );

            return array_map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'ranked_score' => (int) $r->ranked_score,
                'maps' => (int) $r->maps,
                'best' => (int) $r->best,
            ], $rows);
        });

        return [
            'rows' => array_slice($all, max(0, $page - 1) * static::RANKINGS_PAGE, static::RANKINGS_PAGE),
            'total' => count($all),
        ];
    }

    /**
     * Each player's best play of the map in the ruleset (by score, the earlier play on a tie), best first:
     * [['id', 'user_id', 'total_score'], ...].
     */
    private static function bestPerUser(int $beatmapId, int $rulesetId, ?array $userIds, array $mods): array
    {
        $bindings = [$beatmapId, $rulesetId];
        $filter = '';
        if ($userIds !== null) {
            $userIds = array_values(array_unique(array_map('intval', $userIds)));
            $filter .= ' AND s.user_id IN ('.implode(',', array_fill(0, count($userIds), '?')).')';
            array_push($bindings, ...$userIds);
        }
        $mods = array_values(array_unique(array_filter(array_map(fn ($m) => strtoupper((string) $m), $mods), fn ($m) => preg_match('/^[A-Z0-9]{1,8}$/', $m) === 1)));
        if ($mods !== []) {
            $wanted = array_values(array_diff($mods, ['NM']));
            $filter .= ' AND JSON_LENGTH(JSON_EXTRACT(s.data, \'$.mods\')) = ?';
            $bindings[] = count($wanted);
            foreach ($wanted as $acronym) {
                $filter .= ' AND JSON_CONTAINS(JSON_EXTRACT(s.data, \'$.mods[*].acronym\'), JSON_QUOTE(?))';
                $bindings[] = $acronym;
            }
        }

        $rows = DB::select(
            "SELECT id, user_id, total_score FROM (
              SELECT s.id, s.user_id, s.total_score,
                ROW_NUMBER() OVER (PARTITION BY s.user_id ORDER BY s.total_score DESC, s.id) AS place
              FROM scores s
              JOIN phpbb_users u ON u.user_id = s.user_id AND u.user_warnings = 0 AND u.user_type = 0
              WHERE s.beatmap_id = ? AND s.ruleset_id = ? AND s.passed = 1 AND s.preserve = 1 AND s.ranked = 1{$filter}
            ) best WHERE place = 1 ORDER BY total_score DESC, id",
            $bindings,
        );

        return array_map(fn ($r) => ['id' => (int) $r->id, 'user_id' => (int) $r->user_id, 'total_score' => (int) $r->total_score], $rows);
    }
}
