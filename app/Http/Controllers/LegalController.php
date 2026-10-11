<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Libraries\LocaleMeta;
use App\Libraries\TomYumBranding;
use App\Models\Wiki;

class LegalController extends Controller
{
    // TomYum: our own Terms, Privacy and Copyright pages (resources/views/tomyum-legal), in English for
    // every locale, in place of ppy's Legal/ pages from osu-wiki. Those are ppy's and name ppy as the
    // operator, so they (and the other Legal/ pages) aren't shown here.
    // The Rules and Restrictions & Appeals pages are here too (from example.com's docs). Each can come from our
    // own wiki instead (its Legal/ pages), picked in Admin panel > Branding.
    private const PAGES = [
        'copyright' => 'Copyright',
        'privacy' => 'Privacy',
        'restrictions' => 'Restrictions',
        'rules' => 'Rules',
        'terms' => 'Terms',
    ];

    public function show($locale = null, $path = null)
    {
        $validLocale = LocaleMeta::isValid($locale);
        // /legal/Terms comes in with "Terms" as the locale.
        $key = strtolower(rtrim($validLocale ? (string) $path : concat_path([$locale, $path]), '/'));
        $locale = $validLocale ? $locale : app()->getLocale();

        if ($key === '') {
            return ujs_redirect(route('legal', ['locale' => $locale, 'path' => 'Terms']));
        }

        $name = static::PAGES[$key] ?? abort(404);
        // TomYum: hidden in Admin panel > Branding.
        if (!TomYumBranding::legalShown($key)) {
            abort(404);
        }
        $canonical = parse_url(route('legal', ['locale' => $locale, 'path' => $name]), PHP_URL_PATH);
        if (request()->getPathInfo() !== $canonical) {
            return ujs_redirect(route('legal', ['locale' => $locale, 'path' => $name]));
        }

        // TomYum: the wiki's Legal/<name> page when picked in Admin panel > Branding (the built-in page while
        // it's missing, or GitHub can't be reached the first time).
        if (TomYumBranding::legalFromWiki($key)) {
            $wikiPage = Wiki\Page::lookupForController("Legal/{$name}", $locale);
            if ($wikiPage->isVisible()) {
                return ext_view('tomyum-legal.wiki', [
                    'page' => $key,
                    'path' => $name,
                    'titlePrepend' => $name,
                    'wikiPage' => $wikiPage,
                ]);
            }
        }

        // TomYum: the text staff wrote in Admin panel > Branding, else the built-in page.
        $text = TomYumBranding::legalText($key);

        return ext_view($text === null ? 'tomyum-legal.'.$key : 'tomyum-legal.custom', [
            'page' => $key,
            'text' => $text,
            'titlePrepend' => $name,
        ]);
    }

    public function update($locale, $path)
    {
        priv_check('WikiPageRefresh')->ensureCan();

        (new Wiki\Page("Legal/{$path}", $locale))->sync(true);

        return ext_view('layout.ujs-reload', [], 'js');
    }
}
