<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Libraries\LocaleMeta;
use App\Models\Wiki;

class LegalController extends Controller
{
    // TomYum: our own Terms, Privacy and Copyright pages (resources/views/tomyum-legal), in English for
    // every locale, in place of ppy's Legal/ pages from osu-wiki. Those are ppy's and name ppy as the
    // operator, so they (and the other Legal/ pages) aren't shown here.
    // The Rules and Restrictions & Appeals pages are here too (from example.com's docs).
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
        $canonical = parse_url(route('legal', ['locale' => $locale, 'path' => $name]), PHP_URL_PATH);
        if (request()->getPathInfo() !== $canonical) {
            return ujs_redirect(route('legal', ['locale' => $locale, 'path' => $name]));
        }

        return ext_view('tomyum-legal.'.$key, [
            'page' => $key,
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
