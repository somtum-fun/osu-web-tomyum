<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use Auth;
use Carbon\Carbon;
use Request;

/**
 * Admin panel: lazer's main-menu banners (osu!'s menu-content.json), kept by TomYum and served at
 * assets.<domain>/menu-content.json (TomYum's AdminMenuBannerEndpoints; Discord's /banner_add and
 * /banner_remove change the same list). The website's home page shows them too when
 * OSU_URL_MENU_CONTENT_JSON points there.
 */
class MenuBannersController extends Controller
{
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
        $banners = [];
        $problem = null;
        try {
            $response = $this->tomyum()->get('/menu-banners');
            if ($response->successful()) {
                $now = Carbon::now();
                foreach ($response->json() ?? [] as $index => $banner) {
                    $begins = parse_time_to_carbon($banner['begins'] ?? null);
                    $expires = parse_time_to_carbon($banner['expires'] ?? null);
                    $banners[] = [
                        'index' => $index,
                        'image' => $banner['image'],
                        'url' => $banner['url'],
                        'begins' => $begins,
                        'expires' => $expires,
                        'state' => match (true) {
                            $begins !== null && $begins->greaterThan($now) => 'upcoming',
                            $expires !== null && $expires->lessThanOrEqualTo($now) => 'ended',
                            default => 'showing',
                        },
                    ];
                }
            } else {
                $problem = $response->json('error') ?? "The game server said no (HTTP {$response->status()}).";
            }
        } catch (\Throwable $e) {
            $problem = 'The game server didn\'t answer.';
        }

        return ext_view('panel.admin.menu_banners', [
            'banners' => $banners,
            'feedUrl' => $GLOBALS['cfg']['tomyum']['assets_url'].'/menu-content.json',
            'problem' => $problem,
        ]);
    }

    public function store()
    {
        $time = fn (string $key) => present($value = Request::input($key))
            ? Carbon::parse($value, 'UTC')->toIso8601String()
            : null;

        try {
            $begins = $time('begins');
            $expires = $time('expires');
        } catch (\Throwable $e) {
            return $this->back('error', 'That isn\'t a date.');
        }

        return $this->send('/menu-banners', [
            'image' => trim((string) Request::input('image')),
            'url' => trim((string) Request::input('url')),
            'begins' => $begins,
            'expires' => $expires,
        ], 'Added the banner. Players see it the next time their menu checks (within a few minutes).');
    }

    public function destroy($index)
    {
        return $this->send('/menu-banners/remove', ['index' => (int) $index], 'Removed the banner.');
    }

    private function send(string $path, array $body, string $done)
    {
        try {
            $response = $this->tomyum()->post($path, $body);
        } catch (\Throwable $e) {
            return $this->back('error', 'The game server didn\'t answer.');
        }

        return $response->successful()
            ? $this->back('success', $done)
            : $this->back('error', $response->json('error') ?? "The game server said no (HTTP {$response->status()}).");
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.menu-banners.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
