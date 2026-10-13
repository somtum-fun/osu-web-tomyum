<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Libraries\TomYumSeo;

/** TomYum: robots.txt, sitemap.xml and IndexNow's key file (see TomYumSeo). */
class TomYumSeoController extends Controller
{
    public function robots()
    {
        return response(TomYumSeo::robots(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function sitemap()
    {
        return response(TomYumSeo::sitemap(), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function indexNowKey()
    {
        return response(TomYumSeo::indexNowKey(), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
