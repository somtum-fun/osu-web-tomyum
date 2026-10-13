<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\UsernameValidation;
use App\Models\User;
use Auth;
use DB;
use Request;

/**
 * Blacklisted usernames (admins and developers): names nobody can sign up with or rename to, on the
 * website, lazer or stable. They're osu-web's own phpbb_disallow rows, which its username checks
 * read (UsernameValidation::allowedName; `%` matches anything, case doesn't matter). Players who
 * already have such a name keep it.
 */
class UsernamesController extends Controller
{
    private const TABLE = 'phpbb_disallow';

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseUsernames(Auth::user()));

            return $next($request);
        });
    }

    public static function canUseUsernames(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isDev());
    }

    public function index()
    {
        $names = DB::table(static::TABLE)->orderBy('disallow_username')->get();

        // Players whose current name a pattern matches (they keep it; it's only for staff to see).
        $holders = [];
        foreach ($names as $name) {
            $like = str_replace(['\\', '_'], ['\\\\', '\\_'], $name->disallow_username);
            $holders[$name->disallow_id] = User::where('username', 'like', $like)
                ->orderBy('username')->limit(10)->get(['user_id', 'username']);
        }

        $check = trim((string) Request::input('check'));
        $checked = $check === '' ? null : UsernameValidation::allowedName($check);

        return ext_view('panel.usernames', compact('names', 'holders', 'check', 'checked'));
    }

    public function store()
    {
        $name = trim((string) Request::input('username'));
        if ($name === '' || mb_strlen($name) > 255) {
            return $this->back('error', 'Type a username (or a pattern with %).');
        }
        if (!preg_match('/^[A-Za-z0-9\-\[\]_ %]+$/', $name)) {
            return $this->back('error', 'Usernames only have letters, numbers, spaces and - [ ] _ (and % for "anything").');
        }
        if (str_replace('%', '', $name) === '') {
            return $this->back('error', 'That would block every name.');
        }
        $exists = DB::table(static::TABLE)->get()
            ->contains(fn ($row) => strcasecmp($row->disallow_username, $name) === 0);
        if ($exists) {
            return $this->back('error', "\"{$name}\" is already blacklisted.");
        }

        $id = DB::table(static::TABLE)->insertGetId(['disallow_username' => $name]);
        $this->staffLog('username.blacklist', null, "Blacklisted the username \"{$name}\"");

        return $this->back('success', "Blacklisted \"{$name}\": nobody can sign up with it or rename to it now.");
    }

    public function destroy(string $name)
    {
        $row = DB::table(static::TABLE)->where('disallow_id', (int) $name)->first();
        abort_if($row === null, 404);
        DB::table(static::TABLE)->where('disallow_id', $row->disallow_id)->delete();
        $this->staffLog('username.unblacklist', null, "Took \"{$row->disallow_username}\" off the username blacklist");

        return $this->back('success', "\"{$row->disallow_username}\" can be used again.");
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.usernames.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
