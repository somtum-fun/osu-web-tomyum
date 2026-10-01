<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Models\User;
use App\Models\UserBadge;
use Auth;
use Request;

/**
 * Admin panel: profile badges. Adding goes through TomYum (like Discord's /addbadge), which takes an
 * osu! badge file name or a link to any image and fits it to a badge (86×40).
 */
class BadgesController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseSharedPanel(Auth::user())); // TomYum: the admin's and the TC's

            return $next($request);
        });
    }

    public function index()
    {
        $query = trim((string) Request::input('user'));
        $user = $query === '' ? null : (ctype_digit($query) ? User::find((int) $query) : User::lookup($query, 'username'));

        // Everyone with a badge, so they can be found without searching.
        $holders = UserBadge::select('user_id')->selectRaw('COUNT(*) AS n')->groupBy('user_id')->get();
        $holderNames = User::whereIn('user_id', $holders->pluck('user_id'))->pluck('username', 'user_id');

        return ext_view('panel.admin.badges', [
            'query' => $query,
            'user' => $user,
            'badges' => $user === null ? collect() : UserBadge::where('user_id', $user->getKey())->orderBy('awarded')->get(),
            'holders' => $holders->map(fn ($h) => ['id' => $h->user_id, 'name' => $holderNames[$h->user_id] ?? "#{$h->user_id}", 'count' => $h->n])->sortBy('name'),
        ]);
    }

    public function store(string $user)
    {
        $target = User::findOrFail((int) $user);
        $response = $this->tomyum()->post("/users/{$target->getKey()}/badges", [
            'image' => (string) Request::input('image'),
            'description' => (string) Request::input('description'),
            'url' => presence(Request::input('url')),
        ]);

        return $response->successful()
            ? $this->back($target, 'success', 'Badge given. It shows on their profile now.')
            : $this->back($target, 'error', $response->json('error') ?? 'TomYum didn\'t answer.');
    }

    public function destroy(string $user)
    {
        $target = User::findOrFail((int) $user);
        $response = $this->tomyum()->post("/users/{$target->getKey()}/badges/remove", ['image' => (string) Request::input('image')]);

        return $response->successful()
            ? $this->back($target, 'success', 'Badge taken away.')
            : $this->back($target, 'error', $response->json('error') ?? 'TomYum didn\'t answer.');
    }

    private function back(User $user, string $type, string $message)
    {
        return redirect(route(static::sharedPanelPrefix().'badges.index', ['user' => $user->username]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
