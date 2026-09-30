<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Models\Event;
use App\Transformers\EventTransformer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Community → Activity: the whole server's recent activity, the entries players get on their
 * profiles (new #1s and top ranks, medals, maps ranked or loved, supporter, name changes), newest
 * first. The page asks again every few seconds for entries after the newest it has (?after=id)
 * and for older ones with "show more" (?before=id).
 */
class ActivityController extends Controller
{
    const PER_PAGE = 50;

    /** Leaderboard positions up to this count as a "top rank" (the entries go down to #1000). */
    const TOP_RANK = 50;

    /** What each filter shows, as conditions on the entry's text (that's how osu! stores them). */
    const TYPES = ['highlights', 'firsts', 'ranks', 'medals', 'beatmaps', 'supporters', 'names'];

    const RULESETS = ['osu', 'taiko', 'fruits', 'mania'];

    public function index()
    {
        $type = in_array(request('type'), static::TYPES, true) ? request('type') : 'highlights';
        $ruleset = in_array(request('mode'), static::RULESETS, true) ? request('mode') : null;
        $before = get_int(request('before'));
        $after = get_int(request('after'));

        $query = Event::with('user')
            ->where('private', false)
            // Nothing from restricted players (entries about beatmaps have no player).
            ->where(fn ($q) => $q->whereNull('user_id')->orWhereHas('user', fn ($u) => $u->default()))
            ->where(fn ($q) => static::whereType($q, $type, $ruleset));

        if ($after !== null) {
            // Newer entries for the live refresh: oldest first so none are skipped, flipped below.
            $events = $query->where('event_id', '>', $after)->orderBy('event_id')->limit(static::PER_PAGE)->get()->reverse();
        } else {
            if ($before !== null) {
                $query->where('event_id', '<', $before);
            }
            $events = $query->orderByDesc('event_id')->limit(static::PER_PAGE + 1)->get();
        }
        $hasMore = $after === null && $events->count() > static::PER_PAGE;

        $json = [
            'events' => array_values(array_filter(array_map(
                fn (Event $event) => static::eventJson($event),
                $events->take(static::PER_PAGE)->all(),
            ))),
            'has_more' => $hasMore,
            'mode' => $ruleset,
            'type' => $type,
        ];

        if (is_json_request()) {
            return $json;
        }

        return ext_view('activity.index', ['json' => $json]);
    }

    private static function eventJson(Event $event): ?array
    {
        $json = json_item($event, new EventTransformer());
        if (($json['parse_error'] ?? false) || $json['type'] === null) {
            return null;
        }

        $json['avatar_url'] = $event->user?->user_avatar;
        // Where a score was played: stable (true), lazer (false), or not a score (null).
        $json['legacy_score_event'] = $event->legacy_score_event;

        return $json;
    }

    private static function whereType(Builder $query, string $type, ?string $ruleset): void
    {
        $scores = function (Builder $q, string $pattern) use ($ruleset) {
            $q->where('text', 'regexp', $pattern);
            if ($ruleset !== null) {
                // The entry ends with the mode's name, e.g. "(osu!taiko)".
                $q->where('text', 'like', '%('.osu_trans("beatmaps.mode.{$ruleset}").')');
            }
        };
        // "achieved rank #1 on …" (the number is in <b> for the top 50).
        $first = "achieved (<b>)?rank #1(</b>)? on ";
        $top = 'achieved (<b>)?rank #([1-9]|[1-4][0-9]|'.static::TOP_RANK.')(</b>)? on ';
        $medals = fn (Builder $q) => $q->where('text', 'like', '%unlocked the "<b>%');
        $beatmaps = fn (Builder $q) => $q->where(fn ($b) => $b
            ->where('text', 'regexp', 'has just been (ranked|approved|qualified|loved)!$')
            ->orWhere('text', 'like', '%has submitted a new beatmap%')
            ->orWhere('text', 'like', '%has been revived from eternal slumber%'));
        $supporters = fn (Builder $q) => $q->where(fn ($b) => $b
            ->where('text', 'like', '%to support osu!%')
            ->orWhere('text', 'like', '%has become an osu! supporter%')
            ->orWhere('text', 'like', '%has received the gift of osu! supporter%'));
        $names = fn (Builder $q) => $q->where('text', 'like', '%has changed their username to%');

        match ($type) {
            'firsts' => $scores($query, $first),
            'ranks' => $scores($query, $top),
            'medals' => $medals($query),
            'beatmaps' => $beatmaps($query),
            'supporters' => $supporters($query),
            'names' => $names($query),
            // Everything worth a look: #1s (not every rank #800), and all the rest.
            'highlights' => $query->where(fn ($q) => $q
                ->where(fn ($s) => $scores($s, $first))
                ->orWhere(fn ($s) => $medals($s))
                ->orWhere(fn ($s) => $beatmaps($s))
                ->orWhere(fn ($s) => $supporters($s))
                ->orWhere(fn ($s) => $names($s))),
        };
    }
}
