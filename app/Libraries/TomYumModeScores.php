<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Beatmap;
use App\Models\User;
use App\Transformers\BeatmapCompactTransformer;
use App\Transformers\BeatmapsetCompactTransformer;
use Carbon\Carbon;

/**
 * TomYum: Relax/Autopilot scores (TomYum keeps them, the website doesn't) shaped like the website's
 * scores, for the profile's lists in those modes. They link to their map: there's no score page for
 * them here.
 */
class TomYumModeScores
{
    /** lazer's Relax/Autopilot "rulesets" → [the ruleset, TomYum's mode]. */
    public const MODES = [
        'osurx' => ['osu', 4],
        'taikorx' => ['taiko', 5],
        'fruitsrx' => ['fruits', 6],
        'osuap' => ['osu', 8],
    ];

    public static function variant(mixed $name): ?array
    {
        return is_string($name) ? (static::MODES[$name] ?? null) : null;
    }

    /** One page of a list (best, recent or firsts): ['items' => website-shaped scores, 'total' => all of them]. */
    public static function page(User $user, array $variant, string $type, int $offset, int $limit, bool $includeFails = false): array
    {
        $response = (new TomYumAdmin(auth()->user()))->get("/users/{$user->getKey()}/mode-scores/{$variant[1]}", [
            'type' => $type,
            'offset' => $offset,
            'limit' => $limit,
            'includeFails' => $includeFails ? 'true' : 'false',
        ]);
        if (!$response->successful()) {
            return ['items' => [], 'total' => 0];
        }

        $scores = $response->json('scores') ?? [];
        $beatmaps = Beatmap::with('beatmapset')->whereIn('beatmap_id', array_column($scores, 'beatmapId'))->get()->keyBy('beatmap_id');
        $rulesetId = Beatmap::MODES[$variant[0]];

        $items = [];
        foreach ($scores as $index => $score) {
            $beatmap = $beatmaps[$score['beatmapId']] ?? null;
            if ($beatmap === null || $beatmap->beatmapset === null) {
                continue;
            }
            if ($beatmap->playmode !== $rulesetId) {
                $beatmap->convert = true;
                $beatmap->playmode = $rulesetId;
            }
            $weight = $type === 'best' ? 0.95 ** ($offset + $index) : null;
            $counts = [
                'great' => $score['count300'],
                'ok' => $score['count100'],
                'meh' => $score['count50'],
                'miss' => $score['countMiss'],
            ];

            $items[] = [
                'id' => $score['id'],
                'type' => 'solo_score',
                'user_id' => $user->getKey(),
                'accuracy' => $score['accuracy'],
                'beatmap' => json_item($beatmap, new BeatmapCompactTransformer()),
                'beatmap_id' => $beatmap->getKey(),
                'beatmapset' => json_item($beatmap->beatmapset, new BeatmapsetCompactTransformer()),
                'build_id' => null,
                'classic_total_score' => $score['score'],
                'current_user_attributes' => ['pin' => null],
                'ended_at' => json_time(Carbon::parse($score['playedAt'])),
                'has_replay' => false,
                'is_perfect_combo' => $score['perfect'],
                'legacy_perfect' => $score['perfect'],
                'legacy_score_id' => null,
                'legacy_total_score' => $score['score'],
                'max_combo' => $score['maxCombo'],
                'maximum_statistics' => [],
                'mods' => array_map(fn ($acronym) => ['acronym' => $acronym], app('mods')->bitsetToIds($score['mods'])),
                'passed' => $score['passed'],
                'pp' => $score['pp'],
                'preserve' => true,
                'processed' => true,
                'rank' => $score['passed'] ? $score['grade'] : 'F',
                'ranked' => true,
                'replay' => false,
                'ruleset_id' => $rulesetId,
                'started_at' => null,
                'statistics' => $counts,
                'total_score' => $score['score'],
                'weight' => $weight === null ? null : ['percentage' => $weight * 100, 'pp' => $score['pp'] * $weight],
                'tomyum_url' => route('beatmaps.show', ['beatmap' => $beatmap->getKey()]),
            ];
        }

        return ['items' => $items, 'total' => (int) ($response->json('total') ?? 0)];
    }

    /** A profile section (the first page, and whether there's more), as getExtraSection gives it. */
    public static function section(User $user, array $variant, string $type, int $perPage = 5): array
    {
        $page = static::page($user, $variant, $type, 0, $perPage + 1);
        $items = $page['items'];
        $hasMore = count($items) > $perPage;
        if ($hasMore) {
            array_pop($items);
        }

        return ['items' => $items, 'pagination' => ['hasMore' => $hasMore], 'count' => $page['total']];
    }
}
