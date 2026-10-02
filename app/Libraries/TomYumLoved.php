<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Beatmap;
use App\Models\Beatmapset;
use App\Models\Forum\Forum;
use App\Models\Forum\Post;
use App\Models\Forum\Topic;
use App\Models\Forum\TopicPoll;
use App\Models\Group;
use App\Models\TomYumLovedNomination;
use App\Models\TomYumLovedRequest;
use App\Models\TomYumLovedRound;
use App\Models\User;
use Carbon\CarbonImmutable;
use DB;

/**
 * TomYum: Project Loved, as on osu!. The Loved team (captains, each for the modes their group
 * membership has) picks maps into a round; opening the round's voting posts a topic per map in the
 * Project Loved forum, as BanchoBot, with a Yes/No poll (osu-web's own polls, so voting rules and
 * changing a vote work as on any poll); closing the round counts the polls, posts the results in
 * each topic and loves the sets that passed (through Beatmapset::love, which tells the game).
 */
class TomYumLoved
{
    const MODE_NAMES = [0 => 'osu!', 1 => 'osu!taiko', 2 => 'osu!catch', 3 => 'osu!mania'];

    // Index of the poll's options (their poll_option_id).
    const OPTION_YES = 0;
    const OPTION_NO = 1;

    /** The modes (ruleset ids) $user may pick maps for: admins all, the Loved group its modes (all when none are set). */
    public static function captainModes(?User $user): array
    {
        $all = array_keys(static::MODE_NAMES);
        if ($user === null) {
            return [];
        }
        if ($user->isAdmin()) {
            return $all;
        }
        if (!$user->isProjectLoved()) {
            return [];
        }

        $playmodes = $user->findUserGroup(app('groups')->byIdentifier('loved'))?->playmodes ?? [];
        if (count($playmodes) === 0) {
            return $all;
        }

        return array_values(array_filter(array_map(fn ($mode) => Beatmap::MODES[$mode] ?? null, $playmodes), fn ($id) => $id !== null));
    }

    /** Running rounds (opening, closing, settings) is for admins and anyone in the Loved group. */
    public static function canRunRounds(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isProjectLoved());
    }

    /**
     * The Project Loved forum (under Beatmaps), made when missing: staff and the Loved team may start
     * topics there, everyone may reply. Placed after the other Beatmaps forums.
     */
    public static function forum(): Forum
    {
        $forumId = $GLOBALS['cfg']['tomyum']['loved']['forum_id'];
        $forum = Forum::find($forumId);
        if ($forum !== null) {
            return $forum;
        }

        $parent = Forum::find(40);
        DB::transaction(function () use ($forumId, $parent) {
            // After the parent's last forum, the others moved along (phpBB's left/right ordering).
            $after = $parent === null
                ? (int) Forum::where('left_id', '<', 1000)->max('right_id')
                : ((int) Forum::where('parent_id', $parent->getKey())->max('right_id') ?: $parent->left_id);
            Forum::where('left_id', '>', $after)->where('left_id', '<', 1000)->increment('left_id', 2);
            Forum::where('right_id', '>', $after)->where('right_id', '<', 1000)->increment('right_id', 2);

            DB::table('phpbb_forums')->insert([
                'forum_id' => $forumId,
                'parent_id' => $parent?->getKey() ?? 0,
                'left_id' => $after + 1,
                'right_id' => $after + 2,
                'forum_name' => 'Project Loved',
                'forum_desc' => 'Vote on the maps of each Project Loved round.',
                'forum_parents' => $parent === null ? '' : serialize([$parent->getKey() => [$parent->forum_name, $parent->forum_type]]),
                'forum_rules' => '',
                'forum_type' => 1,
                'enable_indexing' => 1,
            ]);

            $options = [];
            foreach (['f_post', 'f_reply', 'f_postcount'] as $option) {
                $options[$option] = DB::table('phpbb_acl_options')->where('auth_option', $option)->value('auth_option_id')
                    ?? DB::table('phpbb_acl_options')->insertGetId(['auth_option' => $option, 'is_global' => 0, 'is_local' => 1, 'founder_only' => 0]);
            }
            $grant = fn (int $groupId, string $option) => DB::table('phpbb_acl_groups')->updateOrInsert([
                'group_id' => $groupId,
                'forum_id' => $forumId,
                'auth_option_id' => $options[$option],
                'auth_role_id' => 0,
            ], ['auth_setting' => 1]);
            foreach (Group::whereIn('identifier', ['admin', 'gmt', 'loved'])->pluck('group_id') as $groupId) {
                foreach (['f_post', 'f_reply', 'f_postcount'] as $option) {
                    $grant($groupId, $option);
                }
            }
            $everyone = Group::where('identifier', 'default')->value('group_id');
            if ($everyone !== null) {
                $grant($everyone, 'f_reply');
                $grant($everyone, 'f_postcount');
            }
        });

        return Forum::findOrFail($forumId);
    }

    /** Who posts the topics and results. */
    public static function poster(): User
    {
        return User::findOrFail($GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id']);
    }

    /**
     * Opens voting: a topic with a poll for every nomination without one, open for $days days.
     * Returns the number of topics made.
     */
    public static function openVoting(TomYumLovedRound $round, int $days): int
    {
        $forum = static::forum();
        $poster = static::poster();
        $made = 0;

        foreach ($round->nominations()->with(['beatmapset', 'captain'])->whereNull('topic_id')->get() as $nomination) {
            if ($nomination->beatmapset === null) {
                continue;
            }
            $poll = (new TopicPoll())->fill([
                'hide_results' => false,
                'length_days' => $days,
                'max_options' => 1,
                'options' => ['Yes', 'No'],
                'title' => 'Should this map be Loved?',
                'vote_change' => true,
            ]);
            $topic = Topic::createNew($forum, [
                'title' => static::topicTitle($nomination),
                'user' => $poster,
                'body' => static::topicBody($nomination),
            ], $poll);
            $nomination->update(['topic_id' => $topic->getKey()]);
            $made++;
        }

        $round->update([
            'state' => 'voting',
            'voting_ends_at' => CarbonImmutable::now()->addDays($days),
        ]);

        return $made;
    }

    /** The votes on a nomination's poll: [yes, no], the counted result once its round is closed. */
    public static function votes(TomYumLovedNomination $nomination): array
    {
        if ($nomination->result_yes !== null && $nomination->round->isClosed()) {
            return [$nomination->result_yes, $nomination->result_no ?? 0];
        }
        $totals = $nomination->topic?->pollOptions->pluck('poll_option_total', 'poll_option_id') ?? collect();

        return [(int) ($totals[static::OPTION_YES] ?? 0), (int) ($totals[static::OPTION_NO] ?? 0)];
    }

    /** Whether [yes, no] passes the round: enough votes, and enough of them yes. */
    public static function passes(TomYumLovedRound $round, int $yes, int $no): bool
    {
        $total = $yes + $no;

        return $total > 0 && $total >= $round->min_votes && $yes * 100 >= $round->threshold * $total;
    }

    /**
     * Closes the round: counts the polls, loves the sets that passed (all their difficulties in the
     * passing modes), and only then ends the polls, records and posts each result and locks the
     * topics. When a set can't be loved nothing else changes: the round keeps voting, and closing again
     * retries (sets loved by the earlier try count as loved). Returns what happened:
     * ['passed' => n, 'failed' => n, 'loved' => [set ids], 'skipped' => [set id => why], 'errors' => [set id => why]].
     */
    public static function close(TomYumLovedRound $round, User $actor): array
    {
        $now = CarbonImmutable::now();
        $summary = ['passed' => 0, 'failed' => 0, 'loved' => [], 'skipped' => [], 'errors' => []];
        $results = [];
        $toLove = [];

        $nominations = $round->nominations()->with(['beatmapset', 'topic.pollOptions'])->get();
        foreach ($nominations as $nomination) {
            [$yes, $no] = static::votes($nomination);
            $passed = static::passes($round, $yes, $no);
            $results[$nomination->getKey()] = [$yes, $no, $passed];
            $summary[$passed ? 'passed' : 'failed']++;
            if ($passed) {
                $toLove[$nomination->beatmapset_id] = [...($toLove[$nomination->beatmapset_id] ?? []), ...$nomination->beatmapIds()];
            }
        }

        foreach ($toLove as $setId => $beatmapIds) {
            $set = Beatmapset::find($setId);
            if ($set === null) {
                $summary['skipped'][$setId] = 'the set is gone';
            } elseif ($set->isLoved()) {
                // Loved by an earlier try at closing (or by hand meanwhile).
                $summary['loved'][] = $setId;
            } elseif (!$set->isLoveable()) {
                $summary['skipped'][$setId] = 'it is '.$set->status().' already';
            } elseif (count($beatmapIds) === 0) {
                $summary['skipped'][$setId] = 'it has no difficulties in the passing modes';
            } else {
                try {
                    $result = $set->love($actor, array_values(array_unique($beatmapIds)));
                    $error = $result['result'] ? null : $result['message'];
                } catch (\Throwable $e) {
                    log_error($e);
                    $error = $e->getMessage();
                }
                // Something after the love failed (covers, notifications): it still counts if it's Loved.
                if ($set->fresh()->isLoved()) {
                    $summary['loved'][] = $setId;
                } else {
                    $summary['errors'][$setId] = $error ?? 'it was not loved';
                }
            }
        }

        if (count($summary['errors']) > 0) {
            return $summary;
        }

        $poster = static::poster();
        foreach ($nominations as $nomination) {
            [$yes, $no, $passed] = $results[$nomination->getKey()];
            $nomination->update(['result_yes' => $yes, 'result_no' => $no, 'passed' => $passed]);

            $topic = $nomination->topic;
            if ($topic === null) {
                continue;
            }
            if ($topic->poll()->isOpen()) {
                // Ends now (a length of 0 would be forever, so a poll from this very second starts a second earlier).
                $start = min($topic->poll_start->getTimestamp(), $now->getTimestamp() - 1);
                $topic->update([
                    'poll_start' => CarbonImmutable::createFromTimestamp($start),
                    'poll_length' => $now->getTimestamp() - $start,
                ]);
            }
            $total = $yes + $no;
            $percent = $total === 0 ? 0 : round($yes * 100 / $total, 2);
            Post::createNew($topic, $poster, $passed
                ? "[b][color=#22DD22]Passed[/color][/b] with {$percent}% ({$yes}:{$no}). This map is now Loved!"
                : "[b][color=#DD2222]Didn't pass[/color][/b] with {$percent}% ({$yes}:{$no}); it needed {$round->threshold}% of at least {$round->min_votes} votes.");
            $topic->lock();
        }
        $round->update(['state' => 'closed', 'closed_at' => $now]);

        return $summary;
    }

    /** How many maps a player may request a day. */
    const REQUESTS_PER_DAY = 5;

    /**
     * $user asks for set $setInput (a link or id; imported from osu! when it isn't on the server yet)
     * to be Loved in mode $rulesetId, because $reason. When the map is asked for already, $user
     * supports that request instead. Returns [ok, message, request].
     *
     * @return array{0: bool, 1: string, 2: ?TomYumLovedRequest}
     */
    public static function request(User $user, string $setInput, int $rulesetId, string $reason): array
    {
        if (!isset(static::MODE_NAMES[$rulesetId])) {
            return [false, 'Pick a mode.', null];
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            return [false, 'Say why it deserves Loved in 10 to 1000 characters.', null];
        }
        $setId = \App\Http\Controllers\Panel\BnController::parseSetId($setInput);
        if ($setId === null) {
            return [false, "That isn't a beatmap set link or id (like https://osu.ppy.sh/beatmapsets/123456).", null];
        }

        $set = Beatmapset::find($setId);
        if ($set === null) {
            if (TomYumLovedRequest::where('user_id', $user->getKey())->where('created_at', '>', now()->subDay())->count() >= static::REQUESTS_PER_DAY) {
                return [false, 'You asked for '.static::REQUESTS_PER_DAY.' maps today already. Support others, or come back tomorrow.', null];
            }
            try {
                $response = (new TomYumAdmin($user))->post("/beatmapsets/{$setId}/import");
            } catch (\Throwable $e) {
                log_error($e);
                $response = null;
            }
            if ($response?->notFound()) {
                return [false, "osu! doesn't have a beatmap set {$setId}.", null];
            }
            $set = $response?->successful() ? Beatmapset::find($setId) : null;
            if ($set === null) {
                return [false, "Couldn't get that set from osu! right now. Try again in a bit.", null];
            }
        }

        $name = "{$set->artist} - {$set->title}";
        $modeName = static::MODE_NAMES[$rulesetId];
        if ($set->isLoved()) {
            return [false, "{$name} is Loved already.", null];
        }
        if (!$set->isLoveable()) {
            return [false, "{$name} is {$set->status()}: only graveyard, WIP and pending maps can be Loved.", null];
        }
        if (!$set->beatmaps()->where('playmode', $rulesetId)->exists()) {
            return [false, "{$name} has no {$modeName} difficulties.", null];
        }
        $inRound = TomYumLovedNomination::where(['beatmapset_id' => $set->getKey(), 'ruleset_id' => $rulesetId])
            ->whereHas('round', fn ($round) => $round->where('state', '!=', 'closed'))
            ->exists();
        if ($inRound) {
            return [false, "{$name} is in the current round already: vote for it there!", null];
        }

        $existing = TomYumLovedRequest::where(['beatmapset_id' => $set->getKey(), 'ruleset_id' => $rulesetId])
            ->whereIn('status', ['open', 'declined'])
            ->latest('id')
            ->first();
        if ($existing?->status === 'declined' && $existing->updated_at > now()->subDays(90)) {
            return [false, "The Loved team declined {$name} for {$modeName} recently: {$existing->decline_reason}", null];
        }
        if ($existing?->isOpen()) {
            $existing->setSupport($user, true);

            return [true, "{$name} was asked for already, so you support that request now.", $existing];
        }

        if (TomYumLovedRequest::where('user_id', $user->getKey())->where('created_at', '>', now()->subDay())->count() >= static::REQUESTS_PER_DAY) {
            return [false, 'You asked for '.static::REQUESTS_PER_DAY.' maps today already. Support others, or come back tomorrow.', null];
        }
        $request = TomYumLovedRequest::create([
            'beatmapset_id' => $set->getKey(),
            'ruleset_id' => $rulesetId,
            'user_id' => $user->getKey(),
            'reason' => $reason,
        ]);
        $request->setSupport($user, true);

        return [true, "Asked for {$name} in {$modeName}. Others can support your request now.", $request];
    }

    /** osu!'s colour for a star rating (resources/js/utils/beatmap-helper.ts getDiffColour), as #rrggbb. */
    public static function difficultyColour(float $rating): string
    {
        if ($rating < 0.1) {
            return '#aaaaaa';
        }
        if ($rating >= 9) {
            return '#000000';
        }
        static $stops = [
            [0.1, '4290fb'], [1.25, '4fc0ff'], [2, '4fffd5'], [2.5, '7cff4f'], [3.3, 'f6f05c'], [4.2, 'ff8068'],
            [4.9, 'ff4e6f'], [5.8, 'c645b8'], [6.7, '6563de'], [7.7, '18158e'], [9, '000000'],
        ];
        for ($i = 1; $rating > $stops[$i][0]; $i++);
        [$fromAt, $from] = $stops[$i - 1];
        [$toAt, $to] = $stops[$i];
        $t = ($rating - $fromAt) / ($toAt - $fromAt);
        // Mixed with gamma 2.2, as d3.interpolateRgb.gamma(2.2) does.
        $channel = function (int $offset) use ($from, $to, $t) {
            $a = hexdec(substr($from, $offset, 2)) ** 2.2;
            $b = hexdec(substr($to, $offset, 2)) ** 2.2;

            return sprintf('%02x', (int) round(($a + ($b - $a) * $t) ** (1 / 2.2)));
        };

        return '#'.$channel(0).$channel(2).$channel(4);
    }

    public static function topicTitle(TomYumLovedNomination $nomination): string
    {
        $set = $nomination->beatmapset;
        $title = '['.static::MODE_NAMES[$nomination->ruleset_id]."] {$set->artist} - {$set->title} by {$set->creator}";

        return mb_strlen($title) > 100 ? mb_substr($title, 0, 99).'…' : $title;
    }

    public static function topicBody(TomYumLovedNomination $nomination): string
    {
        $set = $nomination->beatmapset;
        $setUrl = route('beatmapsets.show', ['beatmapset' => $set->getKey()]);
        $mapper = $set->user_id > 0 && User::find($set->user_id) !== null
            ? '[url='.route('users.show', ['user' => $set->user_id])."]{$set->creator}[/url]"
            : $set->creator;
        $captain = $nomination->captain === null ? null
            : '[url='.route('users.show', ['user' => $nomination->captain_id])."]{$nomination->captain->username}[/url]";

        $body = "[centre][url={$setUrl}][img]{$set->coverURL('cover')}[/img][/url][/centre]\n"
            ."[size=150][b][url={$setUrl}]{$set->artist} - {$set->title}[/url][/b][/size]\n"
            .'mapped by '.$mapper.' · '.static::MODE_NAMES[$nomination->ruleset_id]."\n\n";
        if (present($nomination->description)) {
            $body .= $nomination->description."\n\n";
        }
        if ($captain !== null) {
            $body .= "[i]Picked by {$captain}.[/i]\n";
        }

        $roundUrl = route('loved.rounds.show', ['round' => $nomination->round_id, 'mode' => Beatmap::modeStr($nomination->ruleset_id)]);

        return $body."Vote in the poll above, or on [url={$roundUrl}]the round's page[/url].";
    }
}
