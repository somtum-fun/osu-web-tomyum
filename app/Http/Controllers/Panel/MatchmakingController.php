<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Exceptions\InvariantException;
use App\Models\Beatmap;
use Auth;
use Carbon\Carbon;
use DB;
use Request;

/**
 * Admin panel: lazer's Quick Play and Ranked Play (osu-server-spectator's matchmaking). A pool is a
 * queue players join for one ruleset (and mania key count). The spectator server fills every pool with
 * the ranked featured-artist maps of 1-5 minutes by itself; maps added here join that list (with mods if
 * given), so a ruleset with few such maps still has enough to play. Ranked Play is always 1v1.
 */
class MatchmakingController extends Controller
{
    private const RULESETS = [0 => 'osu!', 1 => 'osu!taiko', 2 => 'osu!catch', 3 => 'osu!mania'];

    /** Ranked Play deals this many maps per match (RankedPlayMatchController.DECK_SIZE). */
    public const RANKED_DECK = 50;

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseAdmin(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $pools = DB::table('matchmaking_pools')->orderBy('ruleset_id')->orderBy('variant_id')->orderBy('type')->get();
        $added = DB::table('matchmaking_pool_beatmaps as p')
            ->join('osu_beatmaps as b', 'b.beatmap_id', '=', 'p.beatmap_id')
            ->join('osu_beatmapsets as s', 's.beatmapset_id', '=', 'b.beatmapset_id')
            ->select('p.id', 'p.pool_id', 'p.beatmap_id', 'p.mods', 'p.rating', 'p.selection_count', 'b.version', 'b.difficultyrating', 's.artist', 's.title')
            ->orderBy('b.difficultyrating')
            ->get()
            ->groupBy('pool_id');

        $automaticMaps = [];
        foreach ($pools as $pool) {
            $automaticMaps[$pool->id] = static::automaticMaps((int) $pool->ruleset_id, (int) $pool->variant_id);
        }

        return ext_view('panel.admin.matchmaking', [
            'pools' => $pools,
            'automaticMaps' => $automaticMaps,
            'added' => $added,
            'automatic' => static::automaticCounts(),
            'rulesets' => static::RULESETS,
        ]);
    }

    public function store()
    {
        $ruleset = (int) Request::input('ruleset');
        $type = Request::input('type') === 'ranked_play' ? 'ranked_play' : 'quick_play';
        if (!isset(static::RULESETS[$ruleset])) {
            return $this->back('error', 'Pick a ruleset.');
        }
        $variant = $ruleset === 3 ? (int) Request::input('keys') : 0;
        if ($ruleset === 3 && ($variant < 1 || $variant > 10)) {
            return $this->back('error', 'osu!mania pools are for one key count (1-10).');
        }
        $name = trim((string) Request::input('name')) ?: ($type === 'ranked_play' ? 'Ranked Play' : 'Quick Play');

        $id = DB::table('matchmaking_pools')->insertGetId([
            'ruleset_id' => $ruleset,
            'variant_id' => $variant,
            'name' => mb_substr($name, 0, 255),
            'type' => $type,
            'active' => true,
            'lobby_size' => $type === 'ranked_play' ? 2 : static::lobbySize(Request::input('lobby_size')),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $this->staffLog('matchmaking.pool_add', $id, "Made the {$type} pool \"{$name}\" for ".static::RULESETS[$ruleset].($ruleset === 3 ? " {$variant}K" : ''));

        return $this->back('success', 'Pool made. Players see it the next time they open Quick Play / Ranked Play.');
    }

    public function update(string $pool)
    {
        $row = $this->pool($pool);
        $changes = ['updated_at' => Carbon::now()];
        if (Request::has('active')) {
            $changes['active'] = Request::boolean('active');
        }
        if (Request::has('lobby_size') && $row->type !== 'ranked_play') {
            $changes['lobby_size'] = static::lobbySize(Request::input('lobby_size'));
        }
        if (present(Request::input('name'))) {
            $changes['name'] = mb_substr(trim((string) Request::input('name')), 0, 255);
        }
        DB::table('matchmaking_pools')->where('id', $row->id)->update($changes);
        $this->staffLog('matchmaking.pool_edit', $row->id, "Changed the matchmaking pool \"{$row->name}\"");

        return $this->back('success', 'Saved.');
    }

    public function destroy(string $pool)
    {
        $row = $this->pool($pool);
        if (DB::table('matchmaking_user_stats')->where('pool_id', $row->id)->exists()) {
            return $this->back('error', 'People have played in this pool (their ratings belong to it): turn it off instead.');
        }
        DB::transaction(function () use ($row) {
            DB::table('matchmaking_pool_beatmaps')->where('pool_id', $row->id)->delete();
            DB::table('matchmaking_pools')->where('id', $row->id)->delete();
        });
        $this->staffLog('matchmaking.pool_remove', $row->id, "Removed the matchmaking pool \"{$row->name}\"");

        return $this->back('success', 'Removed.');
    }

    public function addBeatmap(string $pool)
    {
        $row = $this->pool($pool);
        $beatmapId = static::beatmapId((string) Request::input('beatmap'));
        $beatmap = $beatmapId === null ? null : Beatmap::find($beatmapId);
        if ($beatmap === null) {
            return $this->back('error', 'No such beatmap here. Give its link or id (import it from the BN panel first if needed).');
        }
        if (!in_array($beatmap->approved, [1, 2, 4], true)) {
            return $this->back('error', 'The map needs a leaderboard (ranked, approved or loved).');
        }
        if ((int) $beatmap->playmode !== (int) $row->ruleset_id || ((int) $row->ruleset_id === 3 && (int) $beatmap->diff_size !== (int) $row->variant_id)) {
            return $this->back('error', 'That map is for another ruleset'.($row->ruleset_id === 3 ? ' or key count' : '').' than this pool.');
        }

        try {
            $mods = static::mods((int) $row->ruleset_id, (string) Request::input('mods'));
        } catch (InvariantException $e) {
            return $this->back('error', 'Mods: '.$e->getMessage().'.');
        }

        // The rating the spectator server gives maps it adds itself (from star rating); it adjusts as people play.
        $rating = (int) round(800 + 500 * (exp(0.16 * $beatmap->difficultyrating) - 1));
        DB::table('matchmaking_pool_beatmaps')->insertOrIgnore([
            'pool_id' => $row->id,
            'beatmap_id' => $beatmap->getKey(),
            'mods' => $mods,
            'rating' => $rating,
        ]);
        $this->staffLog('matchmaking.map_add', $beatmap->getKey(), "Added beatmap {$beatmap->getKey()} to the matchmaking pool \"{$row->name}\"");

        return $this->back('success', 'Added. It\'s used from the next match on.');
    }

    public function removeBeatmap(string $pool, string $entry)
    {
        $row = $this->pool($pool);
        DB::table('matchmaking_pool_beatmaps')->where('pool_id', $row->id)->where('id', (int) $entry)->delete();

        return $this->back('success', 'Removed.');
    }

    /**
     * The maps the spectator server puts in each ruleset's pools by itself (its query: ranked or approved,
     * featured artist, 1-5 minutes of play), by "ruleset" and "ruleset:keys" for mania.
     */
    private static function automaticCounts(): array
    {
        $rows = DB::table('osu_beatmaps as b')
            ->join('osu_beatmapsets as s', 's.beatmapset_id', '=', 'b.beatmapset_id')
            ->whereNotNull('s.track_id')
            ->whereNull('b.deleted_at')
            ->whereNull('s.download_disabled_url')
            ->whereBetween('b.approved', [1, 2])
            ->whereBetween('b.hit_length', [60, 300])
            ->groupBy('b.playmode', 'b.diff_size')
            ->select('b.playmode', 'b.diff_size', DB::raw('COUNT(*) as n'))
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $mode = (int) $row->playmode;
            $counts[$mode] = ($counts[$mode] ?? 0) + (int) $row->n;
            if ($mode === 3) {
                $key = '3:'.(int) $row->diff_size;
                $counts[$key] = ($counts[$key] ?? 0) + (int) $row->n;
            }
        }

        return $counts;
    }

    /** The maps the spectator server puts in a pool by itself (its query), easiest first. */
    private static function automaticMaps(int $ruleset, int $variant)
    {
        $query = DB::table('osu_beatmaps as b')
            ->join('osu_beatmapsets as s', 's.beatmapset_id', '=', 'b.beatmapset_id')
            ->whereNotNull('s.track_id')
            ->whereNull('b.deleted_at')
            ->whereNull('s.download_disabled_url')
            ->whereBetween('b.approved', [1, 2])
            ->whereBetween('b.hit_length', [60, 300])
            ->where('b.playmode', $ruleset)
            ->select('b.beatmap_id', 'b.version', 'b.difficultyrating', 'b.hit_length', 's.artist', 's.title')
            ->orderBy('b.difficultyrating');
        if ($ruleset === 3) {
            $query->where('b.diff_size', $variant);
        }

        return $query->get();
    }

    private function pool(string $id): object
    {
        return DB::table('matchmaking_pools')->where('id', (int) $id)->first() ?? abort(404);
    }

    private static function lobbySize(mixed $value): int
    {
        return max(2, min(16, (int) $value ?: 8));
    }

    /** A beatmap id from an id, a /b/ link or a set link's #mode/id. */
    private static function beatmapId(string $text): ?int
    {
        $text = trim($text);
        if (ctype_digit($text)) {
            return (int) $text;
        }

        return preg_match('~(?:/beatmaps/|/b/|#(?:osu|taiko|fruits|mania)/)(\d+)~', $text, $match) === 1 ? (int) $match[1] : null;
    }

    /** "HD HR" as the JSON mod list the spectator server reads ('' for none, which replaces its own entry for the map). */
    private static function mods(int $ruleset, string $text): string
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $text));
        if ($letters === '') {
            return '';
        }
        if (strlen($letters) % 2 !== 0) {
            throw new InvariantException("\"{$text}\" isn't a list of two-letter mods");
        }
        $mods = app('mods')->parseInputArray($ruleset, array_map(fn ($acronym) => ['acronym' => $acronym], array_unique(str_split($letters, 2))));

        return json_encode(array_map(fn ($mod) => ['acronym' => $mod->acronym], $mods));
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.matchmaking.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
