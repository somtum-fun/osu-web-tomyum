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
    /**
     * Relax/Autopilot plays are TomYum's, numbered apart from the website's scores. lazer only knows
     * a score by its id (it fetches /scores/{id} and /scores/{id}/download), so they're given ids from
     * here up, which ScoresController hands back to TomYum. lazer plays use their website score id instead.
     */
    public const ID_OFFSET = 1_000_000_000_000;

    /** TomYum's id of a Relax/Autopilot play the website numbered from ID_OFFSET, or null for a website score. */
    public static function tomyumId(mixed $id): ?int
    {
        $id = get_int($id);

        return $id !== null && $id >= static::ID_OFFSET ? $id - static::ID_OFFSET : null;
    }

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

    /**
     * The Relax/Autopilot mode a player picked as their default (osurx, ...), or null for vanilla.
     * It only counts while its ruleset is still their default ruleset.
     */
    public static function defaultVariant(User $user): ?string
    {
        $name = $user->userProfileCustomization?->tomyum_default_variant;

        return $name !== null && (static::MODES[$name][0] ?? null) === $user->playmode ? $name : null;
    }

    /** Whether <paramref name="name"/> names something a player can star: a ruleset, or a Relax/Autopilot variant. */
    public static function isFavoritableMode(mixed $name): bool
    {
        return is_string($name) && (
            in_array($name, ['osu', 'taiko', 'fruits', 'mania'], true)
            || isset(static::MODES[$name])
        );
    }

    /** A player's starred modes (filtered to currently valid names, order kept), or an empty array. */
    public static function favoriteModes(User $user): array
    {
        $list = $user->userProfileCustomization?->tomyum_favorite_modes ?? [];
        if (!is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $item) {
            if (static::isFavoritableMode($item) && !in_array($item, $out, true)) {
                $out[] = $item;
            }
        }
        return $out;
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
        // Like osu!'s own score pages (Score::visibleUsers): a restricted player's scores are only for admins.
        if (auth()->user()?->isAdmin() !== true && ((int) $user->user_warnings !== 0 || (int) $user->user_type !== 0)) {
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

    /**
     * A map's Relax/Autopilot leaderboard (TomYum's, by pp) for the beatmap page's scoreboard, shaped
     * like BeatmapsController::scores: the top 50 and the viewer's own best. $variant is "rx" or "ap";
     * a ruleset without that variant (mania, or AP outside osu!) has no scores. $mods (acronyms, "NM"
     * for none) keeps plays with exactly those mods, as the in-game "Selected Mods" board does. $type
     * is the website's: global, or only the viewer's country, friends (and themselves) or team. $sort
     * is "pp" or "score" (each player's best play, ordered by score). $stableOnly is the website's
     * stable mode (not lazer's), as on the vanilla board: each player's best stable play, no lazer
     * plays (stable has no custom DT speed, for one).
     */
    public static function beatmapLeaderboard(Beatmap $beatmap, ?string $ruleset, string $variant, array $mods = [], string $type = 'global', string $sort = 'pp', bool $stableOnly = false): array
    {
        $ruleset ??= Beatmap::modeStr($beatmap->playmode);
        $mode = static::variant($ruleset.$variant);
        if ($mode === null || $beatmap->approved <= 0) {
            return ['scores' => []];
        }

        $viewer = auth()->user();
        $userIds = match ($type) {
            'global' => null,
            'country' => $viewer === null ? [] : User::default()->where('country_acronym', $viewer->country_acronym)->pluck('user_id')->all(),
            'friend' => $viewer === null ? [] : [...$viewer->friends()->allRelatedIds(), $viewer->getKey()],
            'team' => $viewer?->team?->members()->pluck('user_id')->all() ?? [],
        };
        if ($userIds === []) {
            return ['scores' => []];
        }

        $query = [
            'limit' => 50,
            'viewerId' => $viewer?->getKey(),
            'mods' => $mods === [] ? null : app('mods')->idsToBitset(array_diff($mods, ['NM'])),
            'sort' => $sort,
            'stableOnly' => $stableOnly ? 'true' : null,
        ];
        $path = "/beatmaps/{$beatmap->getKey()}/mode-scores/{$mode[1]}";
        $admin = new TomYumAdmin($viewer);
        $response = $userIds === null ? $admin->get($path, $query) : $admin->post($path, ['userIds' => $userIds], $query);
        if (!$response->successful()) {
            abort(503, 'The game server is unavailable.');
        }

        $entries = $response->json('scores') ?? [];
        $mine = $response->json('userScore');
        $users = User::with(['country', 'team'])
            ->whereIn('user_id', [...array_column($entries, 'userId'), ...($mine === null ? [] : [$mine['userId']])])
            ->get()
            ->keyBy('user_id');
        $rulesetId = Beatmap::MODES[$mode[0]];

        $row = function (array $entry) use ($users, $beatmap, $rulesetId): ?array {
            $user = $users[$entry['userId']] ?? null;
            if ($user === null) {
                return null;
            }
            $json = static::item($entry['score'], $user, $beatmap, $rulesetId, null);
            $json['user'] = json_item($user, new UserCompactTransformer(), ['country', 'cover', 'team']);

            return $json;
        };

        $result = ['scores' => array_values(array_filter(array_map($row, $entries)))];
        if ($mine !== null && ($score = $row($mine)) !== null) {
            $result['user_score'] = ['position' => $mine['position'], 'score' => $score];
        }

        return $result;
    }

    /**
     * A mode's top plays for the rankings' "Top Plays" with the Relax/Autopilot variant: everyone's
     * best on each map, by pp (TomYum's, up to 1000), shaped like the website's top plays (with the
     * player). Null when the game server can't be reached.
     */
    public static function topPlays(string $ruleset, string $variant): ?array
    {
        $mode = static::variant($ruleset.$variant);
        if ($mode === null) {
            return [];
        }
        $response = (new TomYumAdmin(auth()->user()))->get("/mode-scores/top-plays/{$mode[1]}", ['limit' => 1000]);
        if (!$response->successful()) {
            return null;
        }

        $entries = $response->json('scores') ?? [];
        $users = User::default()->with(['country', 'team'])->whereIn('user_id', array_column($entries, 'userId'))->get()->keyBy('user_id');
        $beatmaps = Beatmap::with('beatmapset')->whereIn('beatmap_id', array_column(array_column($entries, 'score'), 'beatmapId'))->get()->keyBy('beatmap_id');
        $rulesetId = Beatmap::MODES[$mode[0]];
        $items = [];
        foreach ($entries as $entry) {
            $user = $users[$entry['userId']] ?? null;
            $beatmap = $beatmaps[$entry['score']['beatmapId']] ?? null;
            if ($user === null || $beatmap === null || $beatmap->beatmapset === null) {
                continue;
            }
            $json = static::item($entry['score'], $user, $beatmap, $rulesetId, null);
            $json['user'] = json_item($user, new UserCompactTransformer(), ['country', 'team']);
            $items[] = $json;
        }

        return $items;
    }

    /** A TomYum play shaped like the website's scores. */
    private static function item(array $score, User $user, Beatmap $beatmap, int $rulesetId, ?float $weight): array
    {
        if ($beatmap->playmode !== $rulesetId) {
            $beatmap->convert = true;
            $beatmap->playmode = $rulesetId;
        }

        // TomYum: a lazer play keeps the website's own score id, which its replay carries, so lazer
        // matches the downloaded replay to it. Stable plays (and any without one) use ID_OFFSET.
        $id = !($score['stable'] ?? true) && isset($score['osuWebScoreId'])
            ? (int) $score['osuWebScoreId']
            : static::ID_OFFSET + $score['id'];

        return [
            'id' => $id,
            // Not a website score: nothing may report, pin or link to it as one.
            'type' => 'tomyum_score',
            'user_id' => $user->getKey(),
            'accuracy' => $score['accuracy'],
            'beatmap' => json_item($beatmap, new BeatmapCompactTransformer()),
            'beatmap_id' => $beatmap->getKey(),
            'beatmapset' => json_item($beatmap->beatmapset, new BeatmapsetCompactTransformer()),
            // Its own best (TomYum keeps one per map): the site shows pp only for bests.
            'best_id' => $id,
            'build_id' => null,
            'classic_total_score' => $score['score'],
            'current_user_attributes' => ['pin' => null],
            'ended_at' => json_time(Carbon::parse($score['playedAt'])),
            'has_replay' => $score['hasReplay'] ?? false,
            'is_perfect_combo' => $score['perfect'],
            'legacy_perfect' => $score['perfect'],
            // Stable or lazer (the score page's "Played on").
            'legacy_score_id' => ($score['stable'] ?? true) ? $score['id'] : null,
            'legacy_total_score' => $score['score'],
            'max_combo' => $score['maxCombo'],
            // Objects, not arrays, even when empty: lazer reads them as dictionaries.
            'maximum_statistics' => new \stdClass(),
            // osu!stable plays carry the Classic mod, as osu! shows them; lazer's don't.
            'mods' => array_map(
                fn ($acronym) => ['acronym' => $acronym],
                [...app('mods')->bitsetToIds($score['mods']), ...(($score['stable'] ?? true) ? ['CL'] : [])],
            ),
            'passed' => $score['passed'],
            'pp' => $score['pp'],
            'preserve' => true,
            'processed' => true,
            'rank' => $score['passed'] ? $score['grade'] : 'F',
            'ranked' => true,
            'replay' => $score['hasReplay'] ?? false,
            'ruleset_id' => $rulesetId,
            'started_at' => null,
            'statistics' => (object) array_filter([
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
            'tomyum_replay_url' => ($score['hasReplay'] ?? false) ? route('scores.tomyum.download', ['score' => $score['id']]) : null,
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
