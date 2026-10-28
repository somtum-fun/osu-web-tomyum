<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumBranding;
use App\Libraries\TomYumSiteSettings;
use Auth;
use Request;

/**
 * Admin panel: this server's branding (TomYumBranding): its name, logo, favicons, videos and the
 * texts only its team writes. Saved in tomyum_site_settings as brand_*; a field left as its default
 * isn't saved, so a later change of default (in config or code) still reaches it.
 */
class BrandingController extends Controller
{
    /** Texts where blank means "show nothing" rather than "use the default". */
    private const HIDEABLE = [
        'support_note', 'support_signature', 'invoice_seller', 'invoice_contact', 'footer', 'keywords', 'support_team',
        'operator', 'country', 'description', 'privacy_payments', 'privacy_hosting', 'privacy_laws',
    ];

    /** Texts where blank means the default (a button needs a name). */
    private const NAMED = ['downloads_client_name', 'downloads_patcher_name'];

    /** On/off switches (checkboxes), all on by default. */
    private const SWITCHES = ['downloads_show_lazer', 'downloads_show_stable', 'downloads_show_legacy', 'vote_enabled'];

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseAdmin(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $saved = TomYumBranding::saved();
        $logo = $saved['logo'] ?? TomYumBranding::logoChoice();

        return ext_view('panel.admin.branding', [
            'saved' => $saved,
            'name' => TomYumBranding::name(),
            'emailDomain' => TomYumBranding::emailDomain(),
            'firstMembersBefore' => TomYumBranding::firstMembersBefore(),
            'logoChoice' => isset(TomYumBranding::LOGOS[$logo]) ? $logo : 'custom',
            'logoUrl' => isset(TomYumBranding::LOGOS[$logo]) ? '' : $logo,
            'logo' => TomYumBranding::logo(),
            'faviconChoice' => TomYumBranding::faviconChoice(),
            'landingVideoUrl' => TomYumBranding::landingVideoUrl(),
            'downloadVideoUrl' => TomYumBranding::downloadVideoUrl(),
            'downloadsHost' => TomYumBranding::downloadsHost(),
            'switches' => array_combine(static::SWITCHES, array_map(fn ($name) => TomYumBranding::enabled($name), static::SWITCHES)),
            'voteSites' => TomYumBranding::voteSitesText(),
            // Each legal page: builtin, wiki, custom (with its text) or hidden.
            'legal' => array_map(function ($key) use ($saved) {
                $value = $saved["legal_{$key}"];

                return [
                    'mode' => $value === null
                        ? (TomYumBranding::legalFromWiki($key) ? 'wiki' : 'builtin')
                        : (trim($value) === '' ? 'hidden' : 'custom'),
                    'text' => $value ?? '',
                ];
            }, array_combine(array_keys(TomYumBranding::LEGAL_PAGES), array_keys(TomYumBranding::LEGAL_PAGES))),
            // The texts as written (placeholders kept), their default when unset.
            'texts' => array_combine(
                array_keys(TomYumBranding::DEFAULTS),
                array_map(fn ($key) => $saved[$key] ?? TomYumBranding::DEFAULTS[$key], array_keys(TomYumBranding::DEFAULTS)),
            ),
        ]);
    }

    public function update()
    {
        $changed = [];
        $put = function (string $field, ?string $value) use (&$changed) {
            if (TomYumSiteSettings::get("brand_{$field}") !== $value) {
                TomYumSiteSettings::set("brand_{$field}", $value);
                $changed[] = $field;
            }
        };
        $plain = fn (string $key) => presence(trim((string) Request::input($key)));

        $name = $plain('name');
        if ($name !== null && mb_strlen($name) > 50) {
            return $this->back('error', 'The name can be up to 50 characters.');
        }
        $put('name', $name);

        $domain = $plain('email_domain');
        if ($domain !== null && !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
            return $this->back('error', 'The email domain should look like example.com (no @).');
        }
        $put('email_domain', $domain === null ? null : strtolower($domain));

        $firstMembersBefore = $plain('first_members_before');
        if ($firstMembersBefore !== null && !TomYumBranding::isValidDate($firstMembersBefore)) {
            return $this->back('error', 'The "Here since the beginning" date should be YYYY-MM-DD (or empty to turn it off).');
        }
        $put('first_members_before', $firstMembersBefore);

        $logo = (string) Request::input('logo');
        if ($logo === 'custom') {
            $url = $plain('logo_url');
            $ratio = (float) Request::input('logo_ratio');
            if ($url === null || !preg_match('#^(https?://|/)#', $url)) {
                return $this->back('error', 'Give the custom logo\'s address (https://… or /images/…).');
            }
            if ($ratio <= 0 || $ratio > 10) {
                return $this->back('error', 'Give the custom logo\'s width ÷ height (1 for a square, 2.67 for 480×180).');
            }
            $put('logo', $url);
            $put('logo_ratio', (string) round($ratio, 4));
        } elseif (isset(TomYumBranding::LOGOS[$logo])) {
            $put('logo', $logo);
            $put('logo_ratio', null);
        }

        $favicon = (string) Request::input('favicon');
        if (isset(TomYumBranding::FAVICONS[$favicon])) {
            $put('favicon', $favicon);
        }

        foreach (['landing_video_url', 'download_video_url', 'downloads_host', 'downloads_lazer_url', 'downloads_patcher_url'] as $field) {
            $url = $plain($field);
            if ($url !== null && !preg_match('#^(https?://|/)#', $url)) {
                return $this->back('error', 'Addresses (videos, downloads) should start with https:// or /.');
            }
            $put($field, $url);
        }

        foreach (static::NAMED as $field) {
            $value = $plain($field);
            $put($field, $value === null || $value === TomYumBranding::DEFAULTS[$field] ? null : mb_substr($value, 0, 100));
        }

        foreach (static::SWITCHES as $field) {
            // Saved only when off, so "on" follows the default.
            $put($field, Request::boolean($field) ? null : '0');
        }

        // Server lists to vote on: "name | url | rewarded" a line; left as the default, not saved.
        $voteSites = str_replace("\r\n", "\n", trim((string) Request::input('vote_sites')));
        foreach (preg_split('/\R/', $voteSites) as $line) {
            $url = trim(explode('|', $line)[1] ?? '');
            if ($url !== '' && !preg_match('#^https?://#', $url)) {
                return $this->back('error', "A vote link should start with https:// (\"{$line}\").");
            }
        }
        $put('vote_sites', $voteSites === TomYumBranding::voteSitesText(TomYumBranding::defaultVoteSites()) ? null : $voteSites);

        // Legal pages: the built-in one (unset), the wiki's (unset, with source "wiki"), staff's own text, or hidden ('').
        foreach (TomYumBranding::LEGAL_PAGES as $page => [, $title]) {
            $mode = (string) Request::input("legal_{$page}_mode");
            $text = str_replace("\r\n", "\n", trim((string) Request::input("legal_{$page}")));
            if ($mode === 'custom' && $text === '') {
                return $this->back('error', "Write the {$title} page's text, or pick built-in or hidden.");
            }
            $value = match ($mode) {
                'custom' => mb_substr($text, 0, 100000),
                'hidden' => '',
                default => null,
            };
            $before = TomYumSiteSettings::get("brand_legal_{$page}");
            $put("legal_{$page}", $value);
            $put("legal_{$page}_source", $mode === 'wiki' ? 'wiki' : null);
            if ($mode === 'custom' && $before !== $value) {
                TomYumSiteSettings::set("brand_legal_{$page}_updated", now()->toDateString());
            }
        }

        foreach (static::HIDEABLE as $field) {
            $value = str_replace("\r\n", "\n", trim((string) Request::input($field)));
            // Left as the default: not saved, so it follows the default.
            $put($field, $value === TomYumBranding::DEFAULTS[$field] ? null : mb_substr($value, 0, 5000));
        }

        if (count($changed) === 0) {
            return $this->back('success', 'Nothing changed.');
        }
        $this->staffLog('site.branding', null, 'Changed the branding: '.implode(', ', $changed));

        return $this->back('success', 'Saved. Pages show it within a few seconds; mail sender names come from the server\'s settings (MAIL_SENDER).');
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.branding.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
