<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Beatmap;
use App\Models\Beatmapset;
use App\Models\TomYumLovedRequest;
use App\Models\TomYumLovedReview;
use App\Models\User;
use DB;

/**
 * TomYum: Project Loved, kept simple (no rounds or polls). Players ask for a map to be Loved in a
 * mode and say why; others review the request, for or against, with why; open requests are listed
 * by their score. The Loved team (each member for the modes their group membership has; admins for
 * all) loves a request, which loves the set's difficulties in that mode (Beatmapset::love, which
 * tells the game), rejects it, removes it from the list, or blacklists the map. Each with a reason.
 */
class TomYumLoved
{
    const MODE_NAMES = [0 => 'osu!', 1 => 'osu!taiko', 2 => 'osu!catch', 3 => 'osu!mania'];

    /** How many maps a player may ask for a day. */
    const REQUESTS_PER_DAY = 5;

    /** The modes (ruleset ids) $user decides for: admins all, the Loved group its modes (all when none are set). */
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

    /** Anyone on the Loved team (or an admin): sees removed requests and changes the blacklist. */
    public static function isTeam(?User $user): bool
    {
        return count(static::captainModes($user)) > 0;
    }

    public static function canDecide(?User $user, TomYumLovedRequest $request): bool
    {
        return in_array($request->ruleset_id, static::captainModes($user), true);
    }

    /** Why $user can't ask for maps or review requests, if they can't. */
    public static function cannotTakePart(User $user): ?string
    {
        return $user->isRestricted() || $user->isSilenced()
            ? "You can't ask for maps or review requests while restricted or silenced."
            : null;
    }

    /**
     * The set from a link or id, imported from osu! when the server doesn't have it yet.
     *
     * @return array{0: ?Beatmapset, 1: ?string} the set, or why there's none
     */
    public static function findSet(User $user, string $input): array
    {
        $setId = \App\Http\Controllers\Panel\BnController::parseSetId($input);
        if ($setId === null) {
            return [null, "That isn't a beatmap set link or id (like https://osu.ppy.sh/beatmapsets/123456)."];
        }

        $set = Beatmapset::find($setId);
        if ($set !== null) {
            return [$set, null];
        }

        try {
            $response = (new TomYumAdmin($user))->post("/beatmapsets/{$setId}/import");
        } catch (\Throwable $e) {
            log_error($e);
            $response = null;
        }
        if ($response?->notFound()) {
            return [null, "osu! doesn't have a beatmap set {$setId}."];
        }
        $set = $response?->successful() ? Beatmapset::find($setId) : null;

        return $set === null ? [null, "Couldn't get that set from osu! right now. Try again in a bit."] : [$set, null];
    }

    /**
     * $user asks for set $setInput (a link or id) to be Loved in mode $rulesetId, because $reason.
     * One request per map and mode: when it's asked for already, $user is pointed at that request
     * to review it instead. A removed request can be asked for again (it starts over).
     *
     * @return array{0: bool, 1: string, 2: ?TomYumLovedRequest} ok, message, the request (made, or the one that exists)
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
        $blacklisted = $setId === null ? null : DB::table('tomyum_loved_blacklist')->where('beatmapset_id', $setId)->first();
        if ($blacklisted !== null) {
            return [false, "This map can't be asked for: {$blacklisted->reason}", null];
        }
        if ($setId !== null) {
            $existing = TomYumLovedRequest::where(['beatmapset_id' => $setId, 'ruleset_id' => $rulesetId])->first();
            if ($existing !== null && $existing->status !== 'removed') {
                return [false, match ($existing->status) {
                    'open' => 'Someone asked for this map in '.static::MODE_NAMES[$rulesetId].' already: review their request instead.',
                    'loved' => 'This map is Loved already.',
                    default => 'The Loved team rejected this map for '.static::MODE_NAMES[$rulesetId].": {$existing->decision_reason}",
                }, $existing];
            }
        }

        if (TomYumLovedRequest::where('user_id', $user->getKey())->where('created_at', '>', now()->subDay())->count() >= static::REQUESTS_PER_DAY) {
            return [false, 'You asked for '.static::REQUESTS_PER_DAY.' maps today already. Review others, or come back tomorrow.', null];
        }

        [$set, $error] = static::findSet($user, $setInput);
        if ($set === null) {
            return [false, $error, null];
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

        $fields = [
            'decided_at' => null,
            'decision_reason' => null,
            'handled_by' => null,
            'reason' => $reason,
            'score' => 1,
            'status' => 'open',
            'user_id' => $user->getKey(),
        ];
        $request = DB::transaction(function () use ($set, $rulesetId, $fields) {
            $request = TomYumLovedRequest::firstOrNew(['beatmapset_id' => $set->getKey(), 'ruleset_id' => $rulesetId]);
            // A removed request starts over.
            $request->reviews()->delete();
            $request->fill($fields)->save();

            return $request;
        });

        return [true, "Asked for {$name} in {$modeName}. Others can review your request now.", $request];
    }

    /**
     * $user's review of an open request: for ($score 1) or against (-1), because $comment. Saving
     * again changes it. The asker's reason is their review.
     *
     * @return array{0: bool, 1: string} ok, message
     */
    public static function review(User $user, TomYumLovedRequest $request, int $score, string $comment): array
    {
        if (!$request->isOpen()) {
            return [false, "This request is {$request->status}: it can't be reviewed any more."];
        }
        if ($request->user_id === $user->getKey()) {
            return [false, 'You asked for this map: your reason counts as your review.'];
        }
        if ($score !== 1 && $score !== -1) {
            return [false, 'Pick for or against.'];
        }
        $comment = trim($comment);
        if (mb_strlen($comment) < 3 || mb_strlen($comment) > 1000) {
            return [false, 'Say why, in 3 to 1000 characters.'];
        }

        DB::transaction(function () use ($user, $request, $score, $comment) {
            TomYumLovedReview::updateOrCreate(
                ['request_id' => $request->getKey(), 'user_id' => $user->getKey()],
                ['score' => $score, 'comment' => $comment],
            );
            $request->refreshScore();
        });

        return [true, 'Your review is saved.'];
    }

    public static function deleteReview(User $user, TomYumLovedRequest $request): void
    {
        DB::transaction(function () use ($user, $request) {
            $request->reviews()->where('user_id', $user->getKey())->delete();
            $request->refreshScore();
        });
    }

    /**
     * The team's decision on a request, with $reason (shown to players): 'love' loves the set's
     * difficulties in the request's mode, 'reject', 'remove' (off the list; it can be asked for
     * again), or 'reopen' (no reason needed).
     *
     * @return array{0: bool, 1: string} ok, message
     */
    public static function decide(User $user, TomYumLovedRequest $request, string $action, string $reason): array
    {
        $reason = trim($reason);
        if (!in_array($action, ['love', 'reject', 'remove', 'reopen'], true)) {
            return [false, 'Unknown action.'];
        }
        if ($action === 'reopen') {
            if ($request->status === 'loved') {
                return [false, "It's Loved: an admin can take the map out of Loved on its page."];
            }
            $request->update(['status' => 'open', 'handled_by' => null, 'decided_at' => null, 'decision_reason' => null]);

            return [true, 'The request is open again.'];
        }
        if (!$request->isOpen()) {
            return [false, "This request is {$request->status} already."];
        }
        if (mb_strlen($reason) < 3) {
            return [false, 'Give a reason (players see it).'];
        }

        if ($action === 'love') {
            $set = $request->beatmapset;
            if ($set === null) {
                return [false, 'The map is gone from the server.'];
            }
            if (!$set->isLoved()) {
                if (!$set->isLoveable()) {
                    return [false, "It's {$set->status()} now, so it can't be Loved."];
                }
                $beatmapIds = $set->beatmaps()->where('playmode', $request->ruleset_id)->pluck('beatmap_id')->all();
                try {
                    $result = $set->love($user, $beatmapIds);
                } catch (\Throwable $e) {
                    log_error($e);
                    $result = ['result' => false, 'message' => $e->getMessage()];
                }
                // Something after the love failed (covers, notifications): it still counts if it's Loved.
                if (!$set->fresh()->isLoved()) {
                    return [false, "Couldn't love it: ".($result['message'] ?? 'it was not loved')];
                }
            }
        }

        $status = ['love' => 'loved', 'reject' => 'rejected', 'remove' => 'removed'][$action];
        $request->update([
            'status' => $status,
            'handled_by' => $user->getKey(),
            'decided_at' => now(),
            'decision_reason' => mb_substr($reason, 0, 2000),
        ]);

        return [true, match ($status) {
            'loved' => 'Loved! Its '.static::MODE_NAMES[$request->ruleset_id].' difficulties are Loved now.',
            'rejected' => 'The request is rejected.',
            'removed' => "The request is off the list. It can be asked for again.",
        }];
    }

    /**
     * Blacklists set $setInput: nobody can ask for it, and its open requests are removed with the
     * reason.
     *
     * @return array{0: bool, 1: string} ok, message
     */
    public static function blacklist(User $user, string $setInput, string $reason): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            return [false, 'Give a reason.'];
        }
        [$set, $error] = static::findSet($user, $setInput);
        if ($set === null) {
            return [false, $error];
        }

        DB::transaction(function () use ($user, $set, $reason) {
            DB::table('tomyum_loved_blacklist')->updateOrInsert(
                ['beatmapset_id' => $set->getKey()],
                ['reason' => mb_substr($reason, 0, 2000), 'user_id' => $user->getKey(), 'created_at' => now()],
            );
            TomYumLovedRequest::where(['beatmapset_id' => $set->getKey(), 'status' => 'open'])->update([
                'status' => 'removed',
                'handled_by' => $user->getKey(),
                'decided_at' => now(),
                'decision_reason' => "Blacklisted: {$reason}",
            ]);
        });

        return [true, "{$set->artist} - {$set->title} is blacklisted."];
    }
}
