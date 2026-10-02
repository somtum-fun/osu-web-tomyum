<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Models\User;
use App\Models\UserBadge;
use Auth;
use Carbon\CarbonImmutable;
use Request;

/**
 * Admin panel: profile badges. Adding goes through TomYum (like Discord's /addbadge), which takes an
 * osu! badge file name or a link to any image and fits it to a badge (86×40); the day it shows as
 * given, its tooltip and its link can be changed here afterwards.
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
        $holderUsers = User::whereIn('user_id', $holders->pluck('user_id'))->get()->keyBy('user_id');

        return ext_view('panel.admin.badges', [
            'query' => $query,
            'user' => $user,
            'badges' => $user === null ? collect() : $this->badges($user)->orderBy('awarded')->get(),
            'holders' => $holders->map(fn ($h) => [
                'id' => $h->user_id,
                'name' => $holderUsers->get($h->user_id)?->username ?? "#{$h->user_id}",
                'avatar' => $holderUsers->get($h->user_id)?->user_avatar,
                'count' => $h->n,
            ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'today' => CarbonImmutable::now('UTC')->toDateString(),
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

        if (!$response->successful()) {
            return $this->back($target, 'error', $response->json('error') ?? 'TomYum didn\'t answer.');
        }

        // TomYum dates it now; another day picked here replaces that.
        $awarded = static::date(Request::input('awarded'));
        $image = (string) $response->json('image');
        if ($awarded !== null && $image !== '' && $awarded->toDateString() !== CarbonImmutable::now('UTC')->toDateString()) {
            $this->badges($target)->where('image', $image)->update(['awarded' => $awarded]);
        }

        return $this->back($target, 'success', 'Badge given. It shows on their profile now.');
    }

    public function update(string $user)
    {
        $target = User::findOrFail((int) $user);
        $image = (string) Request::input('image');
        $badge = $this->badges($target)->where('image', $image)->first();
        if ($badge === null) {
            return $this->back($target, 'error', 'They don\'t have that badge.');
        }

        $description = trim((string) Request::input('description'));
        if ($description === '') {
            return $this->back($target, 'error', 'Give the badge a tooltip.');
        }
        $url = trim((string) Request::input('url'));
        if ($url !== '' && preg_match('#^https?://\S+$#i', $url) !== 1) {
            return $this->back($target, 'error', 'The link should be a web address (https://…).');
        }
        $awarded = static::date(Request::input('awarded'));
        if ($awarded === null) {
            return $this->back($target, 'error', 'That isn\'t a date.');
        }

        // The time of day it had stays, so badges given on one day keep their order on the profile.
        $time = $badge->awarded?->format('H:i:s') ?? '00:00:00';
        $this->badges($target)->where('image', $image)->update([
            'description' => mb_substr($description, 0, 255),
            'url' => mb_substr($url, 0, 255),
            'awarded' => CarbonImmutable::parse("{$awarded->toDateString()} {$time}", 'UTC'),
        ]);
        $this->staffLog('user.badge_edit', $target->getKey(), "Changed {$target->username}'s badge \"{$description}\" ({$image}), given {$awarded->toDateString()}");

        return $this->back($target, 'success', 'Badge saved.');
    }

    public function destroy(string $user)
    {
        $target = User::findOrFail((int) $user);
        $response = $this->tomyum()->post("/users/{$target->getKey()}/badges/remove", ['image' => (string) Request::input('image')]);

        return $response->successful()
            ? $this->back($target, 'success', 'Badge taken away.')
            : $this->back($target, 'error', $response->json('error') ?? 'TomYum didn\'t answer.');
    }

    /** A day picked in a date field (YYYY-MM-DD, UTC); null when it isn't one. */
    private static function date(mixed $input): ?CarbonImmutable
    {
        $input = trim((string) $input);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input) !== 1) {
            return null;
        }
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $input, 'UTC');

        return $date !== false && $date->toDateString() === $input ? $date : null;
    }

    /** The player's rows in osu_badges (one per image; the model's key is only the player). */
    private function badges(User $user)
    {
        return UserBadge::where('user_id', $user->getKey());
    }

    private function back(User $user, string $type, string $message)
    {
        return redirect(route(static::sharedPanelPrefix().'badges.index', ['user' => $user->username]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
