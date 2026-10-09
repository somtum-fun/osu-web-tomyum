<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: which TumThai (lazer) builds this server lets play online.
 *
 * Staff pick either a minimum build (lazer sends YYYYMMDD in <c>x-api-version</c>, with 0 for a
 * local build) or an exact whitelist, in Admin panel > TumThai versions. A request from a build
 * outside the allowed set is refused with 403 and {@see MESSAGE}; TomYumLazerVersionGate enforces it
 * on the API routes score submission and multiplayer come through.
 *
 * Nothing is enforced unless staff set a minimum (or a whitelist). Settings:
 *   tumthai_min_api_version: integer YYYYMMDD; 0 means no minimum.
 *   tumthai_whitelist:       comma-separated YYYYMMDD; empty means minimum-only.
 *   tumthai_allow_zero:      "1" (default) lets a local build in; "0" blocks it.
 */
class TomYumLazerVersions
{
    public const MESSAGE = 'Realtime online functionality is not supported on this version of the game. Please upgrade to the latest version.';

    public static function minVersion(): int
    {
        return (int) (TomYumSiteSettings::get('tumthai_min_api_version') ?? '0');
    }

    /** @return int[] */
    public static function whitelist(): array
    {
        $raw = (string) (TomYumSiteSettings::get('tumthai_whitelist') ?? '');
        $out = [];
        foreach (preg_split('/[\s,]+/', $raw) ?: [] as $part) {
            if (ctype_digit($part) && $part !== '') {
                $out[] = (int) $part;
            }
        }

        return $out;
    }

    public static function allowLocalBuild(): bool
    {
        return (TomYumSiteSettings::get('tumthai_allow_zero') ?? '1') !== '0';
    }

    /** True when no limit is set: anything plays. */
    public static function unrestricted(): bool
    {
        return static::minVersion() === 0 && static::whitelist() === [];
    }

    public static function allows(int $apiVersion): bool
    {
        if (static::unrestricted()) {
            return true;
        }
        if ($apiVersion === 0) {
            return static::allowLocalBuild();
        }

        $whitelist = static::whitelist();
        if ($whitelist !== []) {
            return in_array($apiVersion, $whitelist, true);
        }

        return $apiVersion >= static::minVersion();
    }
}
