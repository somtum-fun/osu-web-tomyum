<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * TomYum: website settings staff change in the admin panel (tomyum_site_settings), cached for a
 * few minutes since every page reads them. Unset ones fall back to their default.
 */
class TomYumSiteSettings
{
    private const CACHE_KEY = 'tomyum:site-settings';

    /** The development banner's text until staff write their own (markdown: links work). */
    public const DEVELOPMENT_BANNER_DEFAULT = 'This is a development instance of the [osu! website](https://osu.ppy.sh). Please do not login with your osu! credentials.';

    public static function get(string $name, ?string $default = null): ?string
    {
        return static::all()[$name] ?? $default;
    }

    public static function set(string $name, ?string $value): void
    {
        DB::table('tomyum_site_settings')->updateOrInsert(['name' => $name], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget(static::CACHE_KEY);
        request()?->attributes->remove(static::CACHE_KEY);
    }

    /**
     * Every setting, read once per request (the branding's name is in most translated texts). Not
     * kept in a static: Octane workers live across requests, and a change wouldn't show.
     */
    private static function all(): array
    {
        $attributes = request()?->attributes;
        if ($attributes?->has(static::CACHE_KEY)) {
            return $attributes->get(static::CACHE_KEY);
        }

        try {
            $settings = Cache::remember(static::CACHE_KEY, 300, fn () => DB::table('tomyum_site_settings')->pluck('value', 'name')->all());
        } catch (\Throwable $e) {
            // Without the cache or database (the asset build runs artisan commands that translate,
            // and translating reads the branding's name): the defaults, not a failure.
            return [];
        }
        $attributes?->set(static::CACHE_KEY, $settings);

        return $settings;
    }

    /** Whether the development banner shows: the panel's switch, else IS_DEVELOPMENT_DEPLOY. */
    public static function developmentBannerEnabled(): bool
    {
        $enabled = static::get('development_banner_enabled');

        return $enabled === null ? (bool) $GLOBALS['cfg']['osu']['is_development_deploy'] : $enabled === '1';
    }

    public static function developmentBannerMessage(): string
    {
        return static::get('development_banner_message') ?? static::DEVELOPMENT_BANNER_DEFAULT;
    }

    /** The banner's message as HTML: markdown links and emphasis, HTML escaped, on one line. */
    public static function developmentBannerHtml(): string
    {
        $html = trim(markdown_plain(static::developmentBannerMessage()));

        return preg_replace('#^<p>(.*)</p>$#s', '$1', $html);
    }
}
