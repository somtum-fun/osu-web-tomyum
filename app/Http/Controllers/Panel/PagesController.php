<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Models\Beatmap;
use App\Models\Beatmapset;
use App\Models\User;
use Auth;
use Request;

class PagesController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $this->ensure(static::canUseAny($user));

        $ticketCategories = \App\Libraries\TomYumTickets::categoriesFor($user);

        return ext_view('panel.index', [
            'openTickets' => count($ticketCategories) === 0 ? 0
                : \DB::table('tomyum_tickets')->whereIn('category', $ticketCategories)->where('status', 'open')->count(),
            'panels' => [
                'bn' => static::canUseBn($user),
                'gmt' => static::canUseGmt($user),
                'tc' => static::canUseTc($user),
                'loved' => static::canUseLoved($user),
                'admin' => static::canUseAdmin($user),
                'bots' => \App\Libraries\TomYumBots::canManageAll($user),
                'kudosu' => KudosuController::canAdjustKudosu($user),
                'packs' => PacksController::canUsePacks($user),
                'changelog' => ChangelogController::canUseChangelog($user),
                'dan' => DanController::canManageDan($user), // TomYum: osu!dan courses
                'tickets' => TicketsController::canUseTickets($user),
            ],
        ]);
    }

    /**
     * The search box in every panel's header: a player (name, id or profile link), a beatmap or set
     * (id or link) or a score link, opened on the panel page for it the staff member can use (GMT or
     * admin for players, BN for sets), else the site's own page. Ambiguous numbers are players first.
     */
    public function search()
    {
        $user = Auth::user();
        $this->ensure(static::canUseAny($user));
        $query = trim((string) Request::input('q'));
        if ($query === '') {
            return redirect(route('panel.index'));
        }

        $back = fn (string $message) => redirect(url()->previous(route('panel.index')))
            ->with('panel_status', ['type' => 'error', 'message' => $message, 'link' => null]);

        // Links: …/users/{id or name}, …/u/x, …/beatmapsets/{id}, …/s/{id}, …/beatmaps/{id}, …/b/{id}, …/scores/{id}.
        if (preg_match('#/(users|u)/([^/?\#]+)#', $query, $m)) {
            return $this->openPlayer(User::lookupWithHistory(urldecode($m[2])), $back, $query);
        }
        if (preg_match('#/(beatmapsets|s)/(\d+)#', $query, $m)) {
            return $this->openSet((int) $m[2], $back);
        }
        if (preg_match('#/(beatmaps|b)/(\d+)#', $query, $m)) {
            $set = Beatmap::find((int) $m[2])?->beatmapset_id;

            return $set === null ? $back("There's no beatmap {$m[2]} here.") : $this->openSet((int) $set, $back);
        }
        if (preg_match('#/scores/(?:[a-z]+/)?(\d+)#', $query, $m)) {
            return redirect(route('scores.show', ['score' => $m[1]]));
        }

        // A number: a player with that id, else a beatmap set, else a beatmap's set.
        $number = ltrim($query, '#');
        if (ctype_digit($number)) {
            if (($player = User::find((int) $number)) !== null) {
                return $this->openPlayer($player, $back, $query);
            }
            if (Beatmapset::whereKey((int) $number)->exists()) {
                return $this->openSet((int) $number, $back);
            }
            if (($set = Beatmap::find((int) $number)?->beatmapset_id) !== null) {
                return $this->openSet((int) $set, $back);
            }

            return $back("Nothing here has the id {$number}.");
        }

        // A name (also an earlier one), else the players whose names contain it.
        if (($player = User::lookupWithHistory($query, 'username')) !== null) {
            return $this->openPlayer($player, $back, $query);
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
        $matches = User::where('username', 'like', $like)->limit(2)->get();
        if (count($matches) === 1) {
            return $this->openPlayer($matches[0], $back, $query);
        }
        if (static::canUseGmt($user)) {
            return redirect(route('panel.gmt.index', ['q' => $query]));
        }
        if (static::canUseAdmin($user)) {
            return redirect(route('panel.admin.users.index', ['q' => $query]));
        }

        return $back("There's no player called \"{$query}\".");
    }

    private function openPlayer(?User $player, \Closure $back, string $query)
    {
        if ($player === null) {
            return $back("There's no player called \"{$query}\".");
        }
        $me = Auth::user();

        return redirect(match (true) {
            static::canUseGmt($me) => route('panel.gmt.users.show', ['user' => $player->getKey()]),
            static::canUseAdmin($me) => route('panel.admin.users.show', ['user' => $player->getKey()]),
            default => route('users.show', ['user' => $player->getKey()]),
        });
    }

    private function openSet(int $setId, \Closure $back)
    {
        if (!Beatmapset::whereKey($setId)->exists()) {
            return $back("There's no beatmap set {$setId} here.");
        }

        return redirect(static::canUseBn(Auth::user())
            ? route('panel.bn.sets.show', ['set' => $setId])
            : route('beatmapsets.show', ['beatmapset' => $setId]));
    }
}
