<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Models\Rank;
use App\Models\User;
use Auth;
use DB;
use Request;

/**
 * Admin panel: user titles, the line under a player's name on their profile (osu!'s "rank": phpbb_ranks,
 * pointed at by phpbb_users.user_rank). Make titles (optionally linking somewhere), edit them, give
 * them to players, take them away.
 */
class TitlesController extends Controller
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
        $titles = Rank::where('rank_special', 1)->orderBy('rank_title')->get();
        // A player holds a title in either slot (a second title shows after the first).
        $ids = $titles->pluck('rank_id');
        $players = User::whereIn('user_rank', $ids)->orWhereIn('tomyum_user_rank_2', $ids)
            ->orderBy('username')->get(['user_id', 'username', 'user_rank', 'tomyum_user_rank_2']);
        $holders = [];
        foreach ($players as $player) {
            foreach (array_unique(array_filter([$player->user_rank, $player->tomyum_user_rank_2])) as $rankId) {
                $holders[$rankId][] = $player;
            }
        }

        return ext_view('panel.admin.titles', ['titles' => $titles, 'holders' => $holders]);
    }

    public function store()
    {
        $title = trim((string) Request::input('title'));
        $url = presence(trim((string) Request::input('url')));
        $error = $this->invalid($title, $url);
        if ($error !== null) {
            return $this->back('error', $error);
        }
        $rank = Rank::create(['rank_title' => $title, 'rank_min' => 0, 'rank_special' => 1, 'rank_image' => '', 'url' => $url]);
        $this->staffLog('title.add', $rank->getKey(), "Made the title \"{$title}\"");

        return $this->back('success', "Made \"{$title}\". Give it to someone below.");
    }

    public function update(string $title)
    {
        $rank = Rank::where('rank_special', 1)->findOrFail((int) $title);
        $name = trim((string) Request::input('title'));
        $url = presence(trim((string) Request::input('url')));
        $error = $this->invalid($name, $url);
        if ($error !== null) {
            return $this->back('error', $error);
        }

        // Groups find their title by its name (AdminController::groupTitle).
        $old = $rank->rank_title;
        if ($name !== $old && array_intersect([$name, $old], AdminController::GROUP_TITLES) !== []) {
            return $this->back('error', "\"{$old}\" can't be called \"{$name}\": group titles keep their names, because the group finds its title by name.");
        }

        $rank->update(['rank_title' => $name, 'url' => $url]);
        $this->staffLog('title.edit', $rank->getKey(), $name === $old ? "Changed the link of the title \"{$name}\"" : "Renamed the title \"{$old}\" to \"{$name}\"");

        return $this->back('success', "Saved \"{$name}\".");
    }

    public function destroy(string $title)
    {
        $rank = Rank::where('rank_special', 1)->findOrFail((int) $title);
        DB::transaction(function () use ($rank) {
            User::where('user_rank', $rank->getKey())->update(['user_rank' => 0]);
            User::where('tomyum_user_rank_2', $rank->getKey())->update(['tomyum_user_rank_2' => 0]);
            $rank->delete();
        });
        $this->staffLog('title.remove', $rank->getKey(), "Removed the title \"{$rank->rank_title}\"");

        return $this->back('success', 'Removed (and taken from everyone who had it).');
    }

    public function assign()
    {
        $name = trim((string) Request::input('user'));
        $user = $name === '' ? null : (ctype_digit($name) ? User::find((int) $name) : User::lookup($name, 'username'));
        if ($user === null) {
            return $this->back('error', "There's no player called \"{$name}\".");
        }

        // Which of the two titles: the first (user_rank) or the second, shown after it.
        $second = Request::input('slot') === '2';
        $column = $second ? 'tomyum_user_rank_2' : 'user_rank';
        $which = $second ? 'second title' : 'title';

        $titleId = (int) Request::input('title');
        if ($titleId === 0) {
            User::whereKey($user->getKey())->update([$column => 0]);
            $this->staffLog('user.title', $user->getKey(), "Took {$user->username}'s {$which} away");

            return $this->back('success', "{$user->username} has no {$which} now.");
        }

        $rank = Rank::where('rank_special', 1)->findOrFail($titleId);
        User::whereKey($user->getKey())->update([$column => $rank->getKey()]);
        $this->staffLog('user.title', $user->getKey(), "Gave {$user->username} the {$which} \"{$rank->rank_title}\"");

        return $this->back('success', "{$user->username}'s {$which} is \"{$rank->rank_title}\" now.");
    }

    private function invalid(string $title, ?string $url): ?string
    {
        if ($title === '' || mb_strlen($title) > 100) {
            return 'A title needs 1 to 100 characters.';
        }
        if ($url !== null && (mb_strlen($url) > 255 || !preg_match('#^https?://#', $url))) {
            return 'The link should be a web address (https://…).';
        }

        return null;
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.titles.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
