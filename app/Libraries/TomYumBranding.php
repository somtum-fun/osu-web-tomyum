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
            'label' => 'osu!\'s logo (ppy\'s trademark)',
            'image' => '/images/layout/osu-logo.png',
            'image_2x' => '/images/layout/osu-logo@2x.png',
            'nav' => '/images/layout/osu-logo-white.svg',
            'nav_bg' => '/images/layout/osu-logo-triangles.svg',
            'ratio' => 1,
        ],
        // The name itself, drawn as an image (HomeController::brandLogo): no logo file needed.
        'text' => [
            'label' => 'The name as text',
        ],
    ];

    /** Built-in favicon sets (folders under public/images; letter: the name's first letter, drawn). */
    public const FAVICONS = [
        'prod' => ['label' => 'production\'s', 'folder' => '/images/favicon', 'ico' => '/favicon.ico'],
        'osu' => ['label' => 'osu!\'s (ppy\'s trademark)', 'folder' => '/images/favicon-osu', 'ico' => '/images/favicon-osu/favicon.ico'],
        'letter' => ['label' => 'The name\'s first letter', 'folder' => null, 'ico' => null],
    ];

    /** The text logo's and letter favicon's colour (osu-web's pink, the site's own accent). */
    public const TEXT_COLOUR = '#ff66ab';

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
        // The support page's "Run by Volunteers" reason.
        'support_team' => ':brand is a free, unofficial server run in our spare time by a couple of volunteers in Thailand. Nobody here gets paid.',
        // The download page: the lazer build's name, and the stable patcher's.
        'downloads_client_name' => 'lazer-fork',
        'downloads_patcher_name' => ':brand patcher',
    ];

    /** The legal pages (LegalController): their key, path and title. */
    public const LEGAL_PAGES = [
        'rules' => ['Rules', 'Rules'],
        'restrictions' => ['Restrictions', 'Restrictions & Appeals'],
        'terms' => ['Terms', 'Terms of Service'],
        'privacy' => ['Privacy', 'Privacy Policy'],
        'copyright' => ['Copyright', 'Copyright (DMCA)'],
    ];

    /** Every setting the Branding page edits, with what an empty one means. */
    public const FIELDS = [
        'name', 'email_domain', 'logo', 'favicon',
        'landing_video_url', 'download_video_url',
        'support_note', 'support_signature', 'invoice_seller', 'invoice_contact', 'keywords', 'footer',
        'support_team', 'downloads_client_name', 'downloads_patcher_name',
        'downloads_host', 'downloads_lazer_url', 'downloads_patcher_url',
        'downloads_show_lazer', 'downloads_show_stable', 'downloads_show_legacy',
        'vote_enabled', 'vote_sites',
        'legal_rules', 'legal_restrictions', 'legal_terms', 'legal_privacy', 'legal_copyright',
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
        if ($choice === 'text') {
            // ?v= is the name: a renamed server's logo isn't the cached one.
            $version = substr(md5(static::name()), 0, 8);

            return [
                'label' => static::LOGOS['text']['label'],
                'image' => route('brand.logo', ['v' => $version]),
                'image_2x' => route('brand.logo', ['v' => $version]),
                'nav' => route('brand.logo', ['v' => $version, 'white' => 1]),
                'nav_bg' => null,
                'ratio' => static::textLogoWidth() / 100,
            ];
        }
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
        $choice = static::faviconChoice();
        if ($choice === 'letter') {
            // One drawn image (an SVG scales to every size); ?v= is the name, as for the text logo.
            return route('brand.favicon', ['v' => substr(md5(static::name()), 0, 8)], false);
        }
        $set = static::FAVICONS[$choice];

        return $file === null ? $set['ico'] : "{$set['folder']}/{$file}";
    }

    /** The favicons' media type (the letter one is an SVG). */
    public static function faviconType(): string
    {
        return static::faviconChoice() === 'letter' ? 'image/svg+xml' : 'image/png';
    }

    /** The text logo's width in its SVG, its height being 100 (about 46 a character, like a bold sans). */
    public static function textLogoWidth(): int
    {
        return max(100, (int) round(mb_strlen(static::name()) * 46));
    }

    /** The name drawn as an SVG logo: the site's pink, or white for the dark menu bar. */
    public static function textLogoSvg(bool $white): string
    {
        $width = static::textLogoWidth();
        $name = htmlspecialchars(static::name(), ENT_XML1 | ENT_QUOTES);
        $colour = $white ? '#ffffff' : static::TEXT_COLOUR;

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$width} 100" width="{$width}" height="100">
              <text x="0" y="74" textLength="{$width}" lengthAdjust="spacingAndGlyphs" fill="{$colour}"
                font-family="Torus, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-size="80" font-weight="700">{$name}</text>
            </svg>
            SVG;
    }

    /** The name's first letter on the site's pink, as an SVG favicon. */
    public static function letterFaviconSvg(): string
    {
        $letter = htmlspecialchars(mb_strtoupper(mb_substr(static::name(), 0, 1)), ENT_XML1 | ENT_QUOTES);
        $colour = static::TEXT_COLOUR;

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
              <rect width="64" height="64" rx="14" fill="{$colour}"/>
              <text x="32" y="46" text-anchor="middle" fill="#ffffff"
                font-family="Torus, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-size="42" font-weight="700">{$letter}</text>
            </svg>
            SVG;
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

    /** An on/off setting ('1' or '0'), $default when unset. */
    public static function enabled(string $name, bool $default = true): bool
    {
        $value = static::setting($name);

        return $value === null ? $default : $value === '1';
    }

    // ── Download page ──────────────────────────────────────────────────────────────────────────

    /** The file host with manifest.json (the lazer builds, the patcher, the hook, the legacy clients). */
    public static function downloadsHost(): string
    {
        return rtrim(static::setting('downloads_host') ?? presence(env('TOMYUM_DL_URL')) ?? 'https://dl.example.com', '/');
    }

    /** Where the lazer button goes when it isn't the file host's build (null: the file host's). */
    public static function downloadsLazerUrl(): ?string
    {
        return static::setting('downloads_lazer_url');
    }

    /** Where the stable patcher button goes when it isn't the file host's (null: the file host's). */
    public static function downloadsPatcherUrl(): ?string
    {
        return static::setting('downloads_patcher_url');
    }

    // ── Vote page ──────────────────────────────────────────────────────────────────────────────

    public static function voteEnabled(): bool
    {
        return static::enabled('vote_enabled');
    }

    /**
     * The server lists to vote on: [name, url (null: coming soon), rewarded], from the Branding page's
     * lines ("name | url | rewarded"), else config('tomyum.votes.sites').
     */
    public static function voteSites(): array
    {
        $lines = static::setting('vote_sites');
        if ($lines === null) {
            return static::defaultVoteSites();
        }

        $sites = [];
        foreach (preg_split('/\R/', $lines) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if ($parts[0] === '') {
                continue;
            }
            $sites[] = [
                'name' => $parts[0],
                'url' => presence($parts[1] ?? null),
                'rewarded' => strtolower($parts[2] ?? '') === 'rewarded',
            ];
        }

        return $sites;
    }

    /** The vote sites in config('tomyum.votes.sites'), when the Branding page has none. */
    public static function defaultVoteSites(): array
    {
        return $GLOBALS['cfg']['tomyum']['votes']['sites'];
    }

    /** Vote sites (these, else the current ones) as the Branding page writes them, one "name | url | rewarded" a line. */
    public static function voteSitesText(?array $sites = null): string
    {
        return implode("\n", array_map(
            fn ($site) => rtrim(implode(' | ', [$site['name'], $site['url'] ?? '', $site['rewarded'] ? 'rewarded' : '']), ' |'),
            $sites ?? static::voteSites(),
        ));
    }

    // ── Legal pages ────────────────────────────────────────────────────────────────────────────

    /**
     * A legal page's own text (markdown) when staff wrote one; null for the built-in page
     * (tomyum-legal/*, production's wording with this server's name); '' when it's hidden.
     */
    public static function legalText(string $page): ?string
    {
        $value = TomYumSiteSettings::get("brand_legal_{$page}");
        if ($value === null) {
            return null;
        }

        return trim($value) === '' ? '' : strtr($value, [':brand' => static::name(), ':year' => date('Y')]);
    }

    public static function legalShown(string $page): bool
    {
        return static::legalText($page) !== '';
    }

    /** The legal pages that are shown: key => [path, title]. */
    public static function legalPages(): array
    {
        return array_filter(static::LEGAL_PAGES, fn ($page) => static::legalShown($page), ARRAY_FILTER_USE_KEY);
    }

    /** When staff last saved a legal page's own text (null: the built-in one). */
    public static function legalUpdatedAt(string $page): ?string
    {
        return TomYumSiteSettings::get("brand_legal_{$page}_updated");
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
