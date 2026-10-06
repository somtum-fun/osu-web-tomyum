<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: this server's branding: its name (":brand" in the language files, the page titles, mail
 * and legal pages), its logo and favicons, the addresses on its own domain, and the texts only its
 * team can write (the support page's note, the invoice's seller). Staff set them in Admin panel >
 * Branding (TomYumSiteSettings, brand_*); unset ones fall back to config('tomyum.branding'), and the
 * texts to production's, the server this was first written for.
 */
class TomYumBranding
{
    /** Built-in logos: the image (normal, @2x), the white one for the menu bar, its triangles, and width ÷ height. */
    public const LOGOS = [
        'prod' => [
            'label' => 'production wordmark',
            'image' => '/images/layout/prod-logo.png',
            'image_2x' => '/images/layout/prod-logo@2x.png',
            'nav' => '/images/layout/prod-logo@2x.png',
            'nav_bg' => null,
            'ratio' => 480 / 180,
        ],
        'osu' => [
            'label' => 'osu!\'s logo',
            'image' => '/images/layout/osu-logo.png',
            'image_2x' => '/images/layout/osu-logo@2x.png',
            'nav' => '/images/layout/osu-logo-white.svg',
            'nav_bg' => '/images/layout/osu-logo-triangles.svg',
            'ratio' => 1,
        ],
    ];

    /** Built-in favicon sets (folders under public/images). */
    public const FAVICONS = [
        'prod' => ['label' => 'production\'s', 'folder' => '/images/favicon', 'ico' => '/favicon.ico'],
        'osu' => ['label' => 'osu!\'s', 'folder' => '/images/favicon-osu', 'ico' => '/images/favicon-osu/favicon.ico'],
    ];

    /** The texts' defaults: production's (":brand" is the name; :vote_url the vote page). */
    public const DEFAULTS = [
        'support_note' => "Running :brand isn't easy or cheap.\n\n"
            ."We're committed to making :brand a free-to-win experience. We put in a lot of time, effort, "
            ."and technical skill to keep everything running smoothly and constantly improving.\n\n"
            .'If you enjoy playing here and want to support our work, consider supporting us. Your support '
            ."helps us keep :brand alive and growing. Can't pay? You can also earn supporter for free by "
            ."[voting for us](:vote_url).\n\n"
            ."A huge thank you to all our donors - you're the reason we can keep doing this.",
        'support_signature' => 'Server Operator, :brand',
        'invoice_seller' => 'Server Operator',
        'invoice_contact' => 'mind@example.com / +00 000 000 000',
        'keywords' => ':brand, prod, osu! private server, osu private server, thailand, rhythm game, relax, autopilot',
        'footer' => ':brand :year · an unofficial osu! private server, not affiliated with ppy Pty Ltd. osu! is a trademark of ppy Pty Ltd.',
    ];

    /** Every setting the Branding page edits, with what an empty one means. */
    public const FIELDS = [
        'name', 'email_domain', 'logo', 'favicon',
        'landing_video_url', 'download_video_url',
        'support_note', 'support_signature', 'invoice_seller', 'invoice_contact', 'keywords', 'footer',
    ];

    public static function name(): string
    {
        return static::setting('name') ?? $GLOBALS['cfg']['tomyum']['branding']['name'];
    }

    public static function emailDomain(): string
    {
        return static::setting('email_domain')
            ?? $GLOBALS['cfg']['tomyum']['branding']['email_domain']
            ?? TomYumTickets::domain();
    }

    /** An address on this server's domain: email('support') → support@example.com. */
    public static function email(string $local): string
    {
        return $local.'@'.static::emailDomain();
    }

    /** The logo setting: a built-in's key (prod, osu) or an image's address. */
    public static function logoChoice(): string
    {
        return static::setting('logo') ?? $GLOBALS['cfg']['tomyum']['branding']['logo'];
    }

    /** The logo: [image, image_2x, nav, nav_bg, ratio] (a custom image is used for all of them). */
    public static function logo(): array
    {
        $choice = static::logoChoice();
        if (isset(static::LOGOS[$choice])) {
            return static::LOGOS[$choice];
        }

        $ratio = (float) (static::setting('logo_ratio') ?? 1);

        return [
            'label' => 'Custom',
            'image' => $choice,
            'image_2x' => $choice,
            'nav' => $choice,
            'nav_bg' => null,
            'ratio' => $ratio > 0 ? $ratio : 1,
        ];
    }

    /** The CSS custom properties the stylesheets draw the logo with (logo.less, nav2.less and others). */
    public static function cssVariables(): string
    {
        $logo = static::logo();
        $url = fn (?string $path) => $path === null ? 'none' : 'url("'.addcslashes($path, '"\\').'")';

        return implode(';', [
            '--brand-logo:'.$url($logo['image']),
            '--brand-logo-2x:'.$url($logo['image_2x']),
            '--brand-logo-nav:'.$url($logo['nav']),
            '--brand-logo-nav-bg:'.$url($logo['nav_bg']),
            '--brand-logo-ratio:'.round($logo['ratio'], 4),
        ]);
    }

    public static function faviconChoice(): string
    {
        $choice = static::setting('favicon') ?? $GLOBALS['cfg']['tomyum']['branding']['favicon'];

        return isset(static::FAVICONS[$choice]) ? $choice : 'prod';
    }

    /** A favicon file's address: favicon('favicon-32x32.png'), or favicon() for the .ico. */
    public static function favicon(?string $file = null): string
    {
        $set = static::FAVICONS[static::faviconChoice()];

        return $file === null ? $set['ico'] : "{$set['folder']}/{$file}";
    }

    public static function landingVideoUrl(): ?string
    {
        return static::setting('landing_video_url') ?? $GLOBALS['cfg']['osu']['landing']['video_url'];
    }

    public static function downloadVideoUrl(): ?string
    {
        return static::setting('download_video_url') ?? $GLOBALS['cfg']['osu']['urls']['download_video'];
    }

    /**
     * A text setting with its placeholders filled in (:brand, :year, :vote_url); an empty one is ''
     * (hidden), an unset one production's default.
     */
    public static function text(string $name): string
    {
        $value = static::setting($name, emptyIsSet: true) ?? static::DEFAULTS[$name] ?? '';

        return strtr($value, [
            ':brand' => static::name(),
            ':year' => date('Y'),
            ':vote_url' => route('vote'),
        ]);
    }

    /** What the Branding page shows: each field's saved value (null: unset, the default applies). */
    public static function saved(): array
    {
        $saved = [];
        foreach (array_merge(static::FIELDS, ['logo_ratio']) as $field) {
            $saved[$field] = TomYumSiteSettings::get("brand_{$field}");
        }

        return $saved;
    }

    /**
     * A setting staff saved. Blank counts as unset, except for the texts (emptyIsSet), where blank
     * means "show nothing" (no note, no invoice contact).
     */
    private static function setting(string $name, bool $emptyIsSet = false): ?string
    {
        $value = TomYumSiteSettings::get("brand_{$name}");
        if ($value === null) {
            return null;
        }

        return $emptyIsSet || trim($value) !== '' ? $value : null;
    }
}
