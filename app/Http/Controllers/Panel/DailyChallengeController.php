<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Exceptions\InvariantException;
use App\Models\Beatmap;
use App\Models\Multiplayer\DailyChallengeQueueItem;
use App\Models\Multiplayer\Room;
use Auth;
use Carbon\CarbonImmutable;
use DB;
use Request;

/**
 * Admin panel: lazer's daily challenge. The queue osu-web opens a room from every day at 00:05
 * UTC; TomYum keeps it a week long with random ranked maps, and admins pick maps here (a picked map
 * simply takes the place of a random one).
 */
class DailyChallengeController extends Controller
{
    /** Mods players may add when none are given (no rate changes: everyone plays the same song). */
    private const DEFAULT_ALLOWED = [
        0 => 'EZ NF HD HR SD PF FL',
        1 => 'EZ NF HD HR SD PF FL',
        2 => 'EZ NF HD HR SD PF FL',
        3 => 'EZ NF HD FI SD PF FL MR',
    ];

    private const RULESETS = [0 => 'osu!', 1 => 'osu!taiko', 2 => 'osu!catch', 3 => 'osu!mania'];

    /**
     * osu!'s week (TomYum's DailyChallengeOptions.WeeklyStars picks the random maps from it): it
     * starts easy on Thursday and ends hard on Wednesday. ISO weekday (1 = Monday) => [min, max] stars.
     */
    public const WEEKLY_STARS = [
        4 => [3.0, 3.5],
        5 => [3.5, 4.0],
        6 => [4.0, 4.5],
        7 => [4.5, 5.0],
        1 => [5.0, 5.5],
        2 => [5.5, 6.0],
        3 => [6.0, 6.5],
    ];

    /** osu-web's star rating colours (utils/beatmap-helper getDiffColour): stops, blended with gamma 2.2. */
    private const STAR_COLOURS = [
        [0.1, '4290FB'], [1.25, '4FC0FF'], [2, '4FFFD5'], [2.5, '7CFF4F'], [3.3, 'F6F05C'], [4.2, 'FF8068'],
        [4.9, 'FF4E6F'], [5.8, 'C645B8'], [6.7, '6563DE'], [7.7, '18158E'], [9, '000000'],
    ];

    /** The colour osu! gives a star rating, as #rrggbb. */
    public static function starColour(float $stars): string
    {
        $stops = static::STAR_COLOURS;
        if ($stars <= $stops[0][0]) {
            return '#'.$stops[0][1];
        }
        foreach ($stops as $i => [$at, $colour]) {
            if ($stars > $at && isset($stops[$i + 1]) && $stars <= $stops[$i + 1][0]) {
                [$next, $nextColour] = $stops[$i + 1];
                $t = ($stars - $at) / ($next - $at);
                $channel = function (int $offset) use ($colour, $nextColour, $t) {
                    $a = hexdec(substr($colour, $offset, 2));
                    $b = hexdec(substr($nextColour, $offset, 2));
                    $mixed = (($a / 255) ** 2.2 * (1 - $t) + ($b / 255) ** 2.2 * $t) ** (1 / 2.2);

                    return sprintf('%02x', (int) round($mixed * 255));
                };

                return '#'.$channel(0).$channel(2).$channel(4);
            }
        }

        return '#'.end($stops)[1];
    }

    /** [min, max] stars for a day. */
    public static function starsFor(\DateTimeInterface $day): array
    {
        return static::WEEKLY_STARS[(int) $day->format('N')];
    }

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
        $current = Room::where('category', 'daily_challenge')->active()->with('playlist.beatmap.beatmapset')->first();
        $queue = $this->queue()->with('beatmap.beatmapset')->get();

        // The first waiting entry opens today when nothing is open yet, else tomorrow.
        $first = CarbonImmutable::today('UTC')->addDays($current === null ? 0 : 1);

        return ext_view('panel.admin.daily_challenge', [
            'current' => $current,
            'queue' => $queue,
            'firstDay' => $first,
            'rulesets' => static::RULESETS,
            'defaultAllowed' => static::DEFAULT_ALLOWED,
        ]);
    }

    public function store()
    {
        $params = get_params(Request::all(), null, [
            'beatmap:string', 'ruleset:int', 'required_mods:string', 'allowed_mods:string', 'description:string', 'position:string',
        ], ['null_missing' => true]);

        $beatmapId = static::beatmapId($params['beatmap'] ?? '');
        $beatmap = $beatmapId === null ? null : Beatmap::find($beatmapId);
        if ($beatmap === null) {
            return $this->back('error', 'No such beatmap here. Give its link or id (import it from the BN panel first if needed).');
        }
        if (!in_array($beatmap->approved, [1, 2, 4], true)) {
            return $this->back('error', 'The map needs a leaderboard (ranked, approved or loved).');
        }

        $ruleset = $params['ruleset'] ?? $beatmap->playmode;
        if (!isset(static::RULESETS[$ruleset]) || ($beatmap->playmode !== 0 && $ruleset !== $beatmap->playmode)) {
            return $this->back('error', 'That map can\'t be played in '.(static::RULESETS[$ruleset] ?? 'that ruleset').' (only osu! maps convert).');
        }

        try {
            $required = static::mods($ruleset, $params['required_mods'] ?? '');
            $allowed = static::mods($ruleset, presence($params['allowed_mods']) ?? static::DEFAULT_ALLOWED[$ruleset]);
            // A required mod isn't also optional.
            $requiredAcronyms = array_column($required, 'acronym');
            $allowed = array_values(array_filter($allowed, fn ($mod) => !in_array($mod->acronym, $requiredAcronyms, true)));
        } catch (InvariantException $e) {
            return $this->back('error', 'Mods: '.$e->getMessage().'.');
        }

        DB::transaction(function () use ($beatmap, $ruleset, $required, $allowed, $params) {
            $item = DailyChallengeQueueItem::create([
                'beatmap_id' => $beatmap->getKey(),
                'ruleset_id' => $ruleset,
                'required_mods' => $required,
                'allowed_mods' => $allowed,
                'description' => presence($params['description']),
                'order' => 0,
            ]);
            $ids = $this->queue()->pluck('id')->reject(fn ($id) => $id === $item->getKey())->values()->all();
            ($params['position'] ?? 'next') === 'last' ? array_push($ids, $item->getKey()) : array_unshift($ids, $item->getKey());
            $this->renumber($ids);
        });
        $this->staffLog('daily_challenge.add', $beatmap->getKey(), "Queued {$beatmap->beatmapset->artist} - {$beatmap->beatmapset->title} [{$beatmap->version}] for the daily challenge");

        return $this->back('success', 'Queued.');
    }

    public function move(string $item)
    {
        $direction = Request::input('direction') === 'up' ? -1 : 1;
        $ids = $this->queue()->pluck('id')->all();
        $at = array_search((int) $item, $ids, true);
        if ($at === false || !isset($ids[$at + $direction])) {
            return $this->back('error', 'It can\'t move that way.');
        }
        [$ids[$at], $ids[$at + $direction]] = [$ids[$at + $direction], $ids[$at]];
        $this->renumber($ids);

        return $this->back('success', 'Moved.');
    }

    public function destroy(string $item)
    {
        $entry = DailyChallengeQueueItem::whereNull('multiplayer_room_id')->findOrFail((int) $item);
        $entry->delete();
        $this->staffLog('daily_challenge.remove', $entry->beatmap_id, "Took beatmap {$entry->beatmap_id} out of the daily challenge queue");

        return $this->back('success', 'Removed. TomYum fills the queue back up within the hour.');
    }

    private function queue()
    {
        return DailyChallengeQueueItem::whereNull('multiplayer_room_id')->orderBy('order')->orderBy('id');
    }

    /** Writes the waiting entries' order as given. */
    private function renumber(array $ids): void
    {
        foreach (array_values($ids) as $index => $id) {
            DailyChallengeQueueItem::whereKey($id)->update(['order' => $index + 1]);
        }
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

    /** "HD HR" / "HDHR" as osu-web's mod list for the ruleset (checked). */
    private static function mods(int $ruleset, string $text): array
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $text));
        if (strlen($letters) % 2 !== 0) {
            throw new InvariantException("\"{$text}\" isn't a list of two-letter mods");
        }
        $acronyms = array_unique(str_split($letters, 2));
        if ($letters === '') {
            return [];
        }

        return app('mods')->parseInputArray($ruleset, array_map(fn ($acronym) => ['acronym' => $acronym], $acronyms));
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.daily-challenge.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
