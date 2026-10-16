<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\User;
use Cache;

/**
 * TomYum: maintenance mode as the game server holds it (`/_admin/maintenance`; turned on in the admin
 * panel). While it's locked, players who aren't staff (admin, developer, GMT: the game server's staff)
 * can't sign in to lazer, and lazer's API answers them "unavailable" (lazer then keeps reconnecting),
 * except score submission, so plays in progress are still submitted. The website stays up with a
 * banner. Read at most every few seconds; if the game server can't be reached, it's "off".
 */
class TomYumMaintenance
{
    private const CACHE_KEY = 'tomyum:maintenance-status';

    private const CACHE_SECONDS = 5;

    /** ['enabled', 'locked', 'message', 'notice', 'startsAt', 'since', 'byUserId'] */
    public static function status(): array
    {
        return Cache::remember(static::CACHE_KEY, static::CACHE_SECONDS, function () {
            try {
                $response = (new TomYumAdmin(static::actor()))->get('/maintenance');

                return $response->successful() ? $response->json() : static::off();
            } catch (\Throwable $e) {
                return static::off();
            }
        });
    }

    public static function forget(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    public static function isStaff(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        // Membership, not isAdmin()/isDev()/isGMT(): those are false for any OAuth token request
        // (osu-web never lets a token act as staff), which kept staff out of lazer during maintenance.
        // Getting past maintenance gives the token nothing else.
        $groups = app('groups');
        foreach (['admin', 'dev', 'gmt'] as $identifier) {
            $group = $groups->byIdentifier($identifier);
            if ($group !== null && $user->findUserGroup($group) !== null) {
                return true;
            }
        }

        return false;
    }

    /** Whether this user is kept out right now (maintenance is locked and they aren't staff). */
    public static function isLockedFor(?User $user): bool
    {
        return (static::status()['locked'] ?? false) && !static::isStaff($user);
    }

    public static function notice(): string
    {
        return static::status()['notice'] ?? 'The server is under maintenance.';
    }

    private static function actor(): ?User
    {
        return User::find($GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id']);
    }

    private static function off(): array
    {
        return ['enabled' => false, 'locked' => false, 'message' => '', 'notice' => '', 'startsAt' => null, 'since' => null, 'byUserId' => null];
    }
}
