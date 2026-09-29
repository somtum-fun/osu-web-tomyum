<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Beatmap;
use App\Models\User;
use App\Transformers\BeatmapCompactTransformer;
use App\Transformers\BeatmapTransformer;
use App\Transformers\UserCompactTransformer;
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
            $weight = $type === 'best' ? 0.95 ** ($offset + $index) : null;
            $items[] = static::item($score, $user, $beatmap, $rulesetId, $weight);
        }

        return ['items' => $items, 'total' => (int) ($response->json('total') ?? 0)];
    }

    /** One play for its page: [score json, player, map], or null when TomYum doesn't have it. */
    public static function one(int $scoreId): ?array
    {
        $response = (new TomYumAdmin(auth()->user()))->get("/mode-scores/{$scoreId}");
        if (!$response->successful()) {
            return null;
        }
        $data = $response->json();
        $user = User::find($data['userId']);
        $beatmap = Beatmap::with('beatmapset')->find($data['score']['beatmapId']);
        if ($user === null || $beatmap === null || $beatmap->beatmapset === null) {
            return null;
        }

        $variant = collect(static::MODES)->first(fn ($mode) => $mode[1] === $data['mode']) ?? ['osu', $data['mode']];
        $rulesetId = Beatmap::MODES[$variant[0]];
        $json = static::item($data['score'], $user, $beatmap, $rulesetId, null);
        $json['beatmap'] = json_item($beatmap, new BeatmapTransformer(), ['max_combo', 'owners', 'user']);
        $json['user'] = json_item($user, new UserCompactTransformer(), UserCompactTransformer::CARD_INCLUDES);
        $json['rank_global'] = $data['position'];
        $json['has_replay'] = $json['replay'] = (bool) $data['hasReplay'];
        $json['tomyum_replay_url'] = $data['hasReplay'] ? route('scores.tomyum.download', ['score' => $scoreId]) : null;
        $json['replay_views'] = 0;

        return [$json, $user, $beatmap];
    }

    /** A TomYum play shaped like the website's scores. */
    private static function item(array $score, User $user, Beatmap $beatmap, int $rulesetId, ?float $weight): array
    {
        if ($beatmap->playmode !== $rulesetId) {
            $beatmap->convert = true;
            $beatmap->playmode = $rulesetId;
        }

        return [
            'id' => $score['id'],
            // Not a website score: nothing may report, pin or link to it as one.
            'type' => 'tomyum_score',
            'user_id' => $user->getKey(),
            'accuracy' => $score['accuracy'],
            'beatmap' => json_item($beatmap, new BeatmapCompactTransformer()),
            'beatmap_id' => $beatmap->getKey(),
            'beatmapset' => json_item($beatmap->beatmapset, new BeatmapsetCompactTransformer()),
            'best_id' => null,
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
            'statistics' => array_filter([
                'great' => $score['count300'],
                'ok' => $score['count100'],
                'meh' => $score['count50'],
                'miss' => $score['countMiss'],
                'perfect' => $score['countGeki'] ?: null,
                'good' => $score['countKatu'] ?: null,
            ], fn ($value) => $value !== null),
            'total_score' => $score['score'],
            'weight' => $weight === null ? null : ['percentage' => $weight * 100, 'pp' => $score['pp'] * $weight],
            'tomyum_url' => route('scores.tomyum', ['score' => $score['id']]),
            'tomyum_replay_url' => null,
        ];
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
