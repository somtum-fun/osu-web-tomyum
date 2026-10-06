<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\User;
use App\Models\UserAccountHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TomYum: appeal cooldowns (the Restrictions & Appeals page) and the appeal form.
 *
 * When a GMT restricts someone they pick the offense, which sets when the player may appeal:
 * cheating 3 months (12 the second time), multi-accounting and account sharing 2 months, anything
 * else 3 unless staff set a date. Another offense while restricted starts it again from that day,
 * and a denied appeal (closing the appeal ticket as denied) waits 3 more months. Once it's over,
 * the player sends their appeal from the site (/home/appeal); it becomes an appeal ticket that the
 * GMT answer in the staff panel, like one sent by email.
 */
class TomYumAppeals
{
    /** offense => what it's called */
    public const OFFENSES = [
        'cheating' => 'Cheating',
        'multi' => 'Multi-accounting',
        'sharing' => 'Account sharing',
        'other' => 'Something else',
    ];

    public const DEFAULT_MONTHS = 3;
    public const DENIED_MONTHS = 3;

    /** The questions on the appeal form (the page's "what to include"). */
    public const QUESTIONS = [
        'what' => ['What led to your restriction?', 'A short explanation of the rule or rules you broke.'],
        'why' => ['Why did you break the rules?', 'Your reasons, and anything else we should know.'],
        'how' => ['How did you break the rules?', 'Mainly for cheating: what you used, and how. Write "not cheating" if it was something else.'],
        'chance' => ['Why should we give you another chance?', "What you think about it now, and what's different."],
    ];

    /** How long the wait is for an offense ($cheatingOffenses counts this one). */
    public static function months(string $offense, int $cheatingOffenses = 1): int
    {
        return match ($offense) {
            'cheating' => $cheatingOffenses >= 2 ? 12 : 3,
            'multi', 'sharing' => 2,
            default => static::DEFAULT_MONTHS,
        };
    }

    public static function cooldown(int $userId): ?object
    {
        return DB::table('tomyum_appeal_cooldowns')->where('user_id', $userId)->first();
    }

    /**
     * A restriction (or another offense while restricted): the wait starts again from today, as long
     * as the offense's, unless staff gave a date. Returns when they may appeal.
     */
    public static function restricted(User $user, string $offense, ?int $actorId, ?Carbon $appealAfter = null, ?string $note = null): Carbon
    {
        $offense = array_key_exists($offense, static::OFFENSES) ? $offense : 'other';
        $row = static::cooldown($user->getKey());
        $cheating = ($row->cheating_offenses ?? 0) + ($offense === 'cheating' ? 1 : 0);
        $now = Carbon::now();
        $after = $appealAfter ?? $now->copy()->addMonthsNoOverflow(static::months($offense, $cheating));

        DB::table('tomyum_appeal_cooldowns')->updateOrInsert(['user_id' => $user->getKey()], [
            'offense' => $offense,
            'cheating_offenses' => min(255, $cheating),
            'denied_appeals' => $row->denied_appeals ?? 0,
            'restricted_at' => $now,
            'appeal_after' => $after,
            'set_by' => $actorId,
            'note' => $note === null ? null : mb_substr($note, 0, 500),
            'created_at' => $row->created_at ?? $now,
            'updated_at' => $now,
        ]);

        return $after;
    }

    /** Staff moving the date by hand. */
    public static function setDate(User $user, Carbon $appealAfter, ?int $actorId): void
    {
        if (static::currentCooldown($user) === null) {
            static::restricted($user, 'other', $actorId, $appealAfter);

            return;
        }
        DB::table('tomyum_appeal_cooldowns')->where('user_id', $user->getKey())
            ->update(['appeal_after' => $appealAfter, 'set_by' => $actorId, 'updated_at' => Carbon::now()]);
    }

    /** A denied appeal: they may appeal again in 3 months. */
    public static function denied(int $userId, ?int $actorId): ?Carbon
    {
        $user = User::find($userId);
        if ($user === null) {
            return null;
        }
        $after = Carbon::now()->addMonthsNoOverflow(static::DENIED_MONTHS);
        // No cooldown for this restriction yet (or only an earlier one's): start one, keeping the counts.
        if (static::currentCooldown($user) === null) {
            static::restricted($user, 'other', $actorId, $after);
        }
        $row = static::cooldown($userId);
        DB::table('tomyum_appeal_cooldowns')->where('user_id', $userId)->update([
            'appeal_after' => $after,
            'denied_appeals' => min(255, $row->denied_appeals + 1),
            'set_by' => $actorId,
            'updated_at' => Carbon::now(),
        ]);

        return $after;
    }

    /**
     * When a restricted player may appeal, or null when they aren't restricted. Restrictions from
     * before cooldowns were kept wait the usual 3 months from the restriction.
     */
    public static function appealAfter(User $user): ?Carbon
    {
        if (!$user->isRestricted()) {
            return null;
        }
        $row = static::currentCooldown($user);
        if ($row !== null) {
            return Carbon::parse($row->appeal_after);
        }

        return (static::lastRestriction($user)?->timestamp ?? Carbon::now())->copy()->addMonthsNoOverflow(static::DEFAULT_MONTHS);
    }

    /**
     * The cooldown row for the player's current restriction, or null. A row kept from an earlier
     * restriction (they were unrestricted, then restricted again by BanchoBot or the anti-cheat, which
     * don't pick an offense) doesn't count: the row stays only for its counts.
     */
    public static function currentCooldown(User $user): ?object
    {
        $row = static::cooldown($user->getKey());
        if ($row === null) {
            return null;
        }
        $restriction = static::lastRestriction($user);
        // The restrict form writes both at once; a minute covers the clocks of TomYum and the website.
        if ($restriction !== null && Carbon::parse($row->restricted_at)->lt($restriction->timestamp->copy()->subMinute())) {
            return null;
        }

        return $row;
    }

    private static function lastRestriction(User $user): ?UserAccountHistory
    {
        return $user->accountHistories()
            ->where('ban_status', UserAccountHistory::TYPES['restriction'])
            ->orderByDesc('timestamp')
            ->first();
    }

    /** The player's appeal still waiting for an answer, if any. */
    public static function openAppeal(User $user): ?object
    {
        return DB::table('tomyum_tickets')
            ->where('user_id', $user->getKey())
            ->where('category', 'appeal')
            ->where('status', 'open')
            ->orderByDesc('id')
            ->first();
    }

    /** Sends the appeal as an appeal ticket; returns its id. */
    public static function submit(User $user, array $answers): int
    {
        $body = "Appeal sent from the website by {$user->username} (user #{$user->getKey()}).\n";
        $row = static::currentCooldown($user);
        if ($row !== null) {
            $body .= 'Offense: '.(static::OFFENSES[$row->offense] ?? $row->offense)
                .'. Restricted '.Carbon::parse($row->restricted_at)->format('j M Y')
                .", appeal allowed from ".Carbon::parse($row->appeal_after)->format('j M Y')
                .", denied appeals so far: {$row->denied_appeals}.\n";
        }
        foreach (static::QUESTIONS as $key => [$question]) {
            $body .= "\n{$question}\n".trim($answers[$key] ?? '')."\n";
        }

        return TomYumTickets::createFromSite($user, 'appeal', "Appeal: {$user->username}", $body);
    }
}
