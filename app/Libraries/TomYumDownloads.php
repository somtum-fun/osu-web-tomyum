<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Libraries;

use Cache;
use Exception;
use Http;

/**
 * TomYum: what the download page offers, from the file host's manifest.json (Admin panel > Branding,
 * or TOMYUM_DL_URL): a lazer build for Windows and Linux, a stable patcher, a hook, and legacy clients.
 * Without a file host the page only links what Branding sets.
 */
class TomYumDownloads
{
    const PATCHER_URL = null;

    public static function baseUrl(): string
    {
        // TomYum: Admin panel > Branding, else TOMYUM_DL_URL.
        return TomYumBranding::downloadsHost();
    }

    public static function manifest(): array
    {
        // Per host: changing it in the Branding page shows the new host's files at once.
        $cacheKey = 'tomyum_downloads_manifest:'.md5(static::baseUrl());
        if (static::baseUrl() === '') {
            return [];
        }

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $manifest = Http::timeout(5)->get(static::baseUrl().'/manifest.json')->throw()->json();
        } catch (Exception $e) {
            $manifest = null;
        }

        if (!is_array($manifest)) {
            // Try again in a minute; the page still has the patcher and a link to the file host.
            Cache::put($cacheKey, [], 60);

            return [];
        }

        Cache::put($cacheKey, $manifest, 600);

        return $manifest;
    }

    /** The legacy clients by year, newest first. */
    public static function legacyByYear(array $manifest): array
    {
        $years = [];
        foreach ($manifest['legacy'] ?? [] as $build) {
            $years[$build['year']][] = $build;
        }
        krsort($years);

        return $years;
    }

    public static function size(?int $bytes): string
    {
        return $bytes === null ? '' : number_format($bytes / 1048576, 1).' MB';
    }
}
