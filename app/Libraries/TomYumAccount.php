<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\User;
use Carbon\Carbon;
use DB;
use Request;

/**
 * TomYum: staff flags on an account (tomyum_account_flags) and its login history
 * (tomyum_login_history). The game server reads the same tables: a locked account can't log in to
 * stable either, and a whitelisted one is left alone by anti-cheat.
 */
class TomYumAccount
{
    /** How long logins are kept. */
    private const HISTORY_DAYS = 180;

    /** The account's flags row, or null when it has none. */
    public static function flags(int $userId): ?object
    {
        return DB::table('tomyum_account_flags')->where('user_id', $userId)->first();
    }

    public static function isLocked(int $userId): bool
    {
        return DB::table('tomyum_account_flags')->where('user_id', $userId)->whereNotNull('locked_at')->exists();
    }

    public static function setLocked(User $user, ?User $actor, ?string $reason): void
    {
        static::setFlag($user->getKey(), [
            'locked_at' => $reason === null ? null : Carbon::now(),
            'locked_by' => $reason === null ? null : $actor?->getKey(),
            'lock_reason' => $reason,
        ]);
    }

    public static function setWhitelisted(User $user, ?User $actor, ?string $reason): void
    {
        static::setFlag($user->getKey(), [
            'whitelisted_at' => $reason === null ? null : Carbon::now(),
            'whitelisted_by' => $reason === null ? null : $actor?->getKey(),
            'whitelist_reason' => $reason,
        ]);
    }

    /** Records a login from this request (the website, or lazer through the API). Never fails the login. */
    public static function recordLogin(User $user, string $client, string $result = 'ok'): void
    {
        try {
            $request = Request::instance();
            DB::table('tomyum_login_history')->insert([
                'user_id' => $user->getKey(),
                'client' => $client,
                'result' => $result,
                'ip' => $request->getClientIp(),
                'country' => presence($request->header('CF-IPCountry')),
                'agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
                'created_at' => Carbon::now(),
            ]);
            // Every so often, forget old logins.
            if (random_int(1, 200) === 1) {
                DB::table('tomyum_login_history')->where('created_at', '<', Carbon::now()->subDays(static::HISTORY_DAYS))->delete();
            }
        } catch (\Throwable $e) {
            log_error($e);
        }
    }

    public static function loginHistory(int $userId, int $limit = 50): array
    {
        return DB::table('tomyum_login_history')->where('user_id', $userId)->orderByDesc('id')->limit($limit)->get()->all();
    }

    private static function setFlag(int $userId, array $values): void
    {
        DB::table('tomyum_account_flags')->updateOrInsert(['user_id' => $userId], $values);
    }
}
