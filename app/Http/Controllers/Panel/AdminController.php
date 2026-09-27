<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Exceptions\ChangeUsernameException;
use App\Libraries\User\PasswordHelper;
use App\Models\Country;
use App\Models\Group;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Str;
use Request;

/**
 * Admin panel: server dashboard, and editing any account (name, email, country, password,
 * groups, supporter, deletion). Changes reach the game right away through TomYum and are logged.
 */
class AdminController extends Controller
{
    /** Groups that aren't assigned by hand. */
    private const UNASSIGNABLE_GROUPS = ['default', 'bot'];

    private const PLAYMODES = ['osu' => 'osu!', 'taiko' => 'osu!taiko', 'fruits' => 'osu!catch', 'mania' => 'osu!mania'];

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
        $stats = $this->tomyum()->get('/stats');
        $online = $this->tomyum()->get('/online');
        $log = $this->tomyum()->get('/log', ['limit' => 30]);
        $entries = $log->successful() ? $log->json() : [];

        return ext_view('panel.admin.index', [
            'server' => $stats->successful() ? $stats->json() : null,
            'online' => $online->successful() ? $online->json() : [],
            'counts' => [
                'Players' => User::where('user_type', '<>', 1)->count(),
                'Restricted' => User::where(fn ($q) => $q->where('user_warnings', '>', 0)->orWhere('user_type', 1))->count(),
                'Scores (24h)' => DB::table('scores')->where('ended_at', '>=', Carbon::now()->subDay())->count(),
                'Beatmap sets' => DB::table('osu_beatmapsets')->whereNull('deleted_at')->count(),
            ],
            'entries' => $entries,
            'actors' => BnController::actorNames($entries),
        ]);
    }

    public function users()
    {
        $query = trim(Request::input('q') ?? '');
        $users = collect();
        if ($query !== '') {
            $exact = ctype_digit($query) ? User::find((int) $query) : User::lookup($query, 'username');
            if ($exact !== null) {
                return redirect(route('panel.admin.users.show', ['user' => $exact->getKey()]));
            }
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
            $users = User::where('username', 'like', $like)->orWhere('user_email', 'like', $like)->orderBy('username')->limit(50)->get();
        }

        return ext_view('panel.admin.users', ['query' => $query, 'users' => $users]);
    }

    public function show(string $user)
    {
        $target = User::findOrFail((int) $user);
        $log = $this->tomyum()->get('/log', ['targetId' => $target->getKey(), 'limit' => 50]);
        $entries = array_values(array_filter(
            $log->successful() ? $log->json() : [],
            fn ($entry) => !str_starts_with($entry['action'], 'beatmapset.'),
        ));

        return ext_view('panel.admin.user', [
            'target' => $target,
            'isSelf' => $target->getKey() === Auth::user()->getKey(),
            'groups' => Group::whereNotIn('identifier', static::UNASSIGNABLE_GROUPS)->orderByRaw('display_order IS NULL, display_order')->get(),
            'memberships' => $target->userGroups()->get()->keyBy('group_id'),
            'countries' => Country::orderBy('name')->pluck('name', 'acronym'),
            'playmodes' => static::PLAYMODES,
            'entries' => $entries,
            'actors' => BnController::actorNames($entries),
        ]);
    }

    public function profile(string $user)
    {
        $target = User::findOrFail((int) $user);
        $changes = [];

        $username = trim(Request::input('username') ?? '');
        if ($username !== '' && $username !== $target->username) {
            $old = $target->username;
            try {
                $target->changeUsername($username, 'admin');
            } catch (ChangeUsernameException $e) {
                return $this->back($target, 'error', 'Username not changed: '.implode(' ', $e->getErrors()->allMessages()));
            }
            $changes[] = "renamed {$old} → {$username}";
        }

        $email = trim(Request::input('email') ?? '');
        if ($email !== '' && $email !== $target->user_email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->back($target, 'error', 'That email address isn\'t valid.');
            }
            if (User::where('user_email', $email)->where('user_id', '<>', $target->getKey())->exists()) {
                return $this->back($target, 'error', 'Another account already uses that email.');
            }
            $target->update(['user_email' => $email]);
            $changes[] = 'email changed';
        }

        $country = strtoupper(trim(Request::input('country') ?? ''));
        if ($country !== '' && $country !== $target->country_acronym) {
            if (!Country::where('acronym', $country)->exists()) {
                return $this->back($target, 'error', 'Unknown country.');
            }
            $target->update(['country_acronym' => $country]);
            $changes[] = "country → {$country}";
        }

        if (count($changes) === 0) {
            return $this->back($target, 'error', 'Nothing changed.');
        }

        return $this->done($target->fresh(), 'admin.profile', ucfirst(implode(', ', $changes)).'.');
    }

    public function password(string $user)
    {
        $target = User::findOrFail((int) $user);
        $password = Request::input('password') ?? '';
        if (strlen($password) < 8) {
            return $this->back($target, 'error', 'Passwords need at least 8 characters.');
        }

        // Same hash the website and the game check: bcrypt(md5(password)).
        $target->update(['user_password' => PasswordHelper::make($password)]);

        return $this->done($target, 'admin.password', "Set a new password for {$target->username}.", 'Password changed. Tell them the new one privately.');
    }

    public function groups(string $user)
    {
        $target = User::findOrFail((int) $user);
        $wanted = Request::input('groups') ?? [];
        $modes = Request::input('playmodes') ?? [];
        $added = [];
        $removed = [];

        foreach (Group::whereNotIn('identifier', static::UNASSIGNABLE_GROUPS)->get() as $group) {
            $want = in_array($group->identifier, $wanted, true);
            $has = $target->findUserGroup($group) !== null;

            // An admin can't remove their own admin group (so there's always a way back in).
            if (!$want && $has && $group->identifier === 'admin' && $target->getKey() === Auth::user()->getKey()) {
                continue;
            }

            if ($want) {
                $playmodes = $group->has_playmodes ? array_values(array_intersect(array_keys(static::PLAYMODES), $modes[$group->identifier] ?? [])) : null;
                if (!$has || $group->has_playmodes) {
                    $target->addToGroup($group, $playmodes, Auth::user());
                    if (!$has) {
                        $added[] = $group->short_name;
                    }
                }
            } elseif ($has) {
                $target->removeFromGroup($group, Auth::user());
                $removed[] = $group->short_name;
            }
        }

        if (count($added) + count($removed) === 0) {
            return $this->done($target, 'admin.groups', "Updated {$target->username}'s group modes.", 'Groups saved.');
        }

        $summary = trim((count($added) > 0 ? 'added '.implode(', ', $added).' ' : '').(count($removed) > 0 ? 'removed '.implode(', ', $removed) : ''));

        return $this->done($target, 'admin.groups', "{$target->username}: {$summary}", 'Groups saved: '.$summary.'.');
    }

    public function supporter(string $user)
    {
        $target = User::findOrFail((int) $user);

        if (Request::input('action') === 'remove') {
            $target->update(['osu_subscriber' => false, 'osu_subscriptionexpiry' => null]);

            return $this->done($target, 'admin.supporter', "Removed {$target->username}'s supporter.", 'Supporter removed (their supporter history is kept).');
        }

        $months = (int) Request::input('months');
        if ($months < 1 || $months > 120) {
            return $this->back($target, 'error', 'Give between 1 and 120 months.');
        }

        DB::transaction(function () use ($target, $months) {
            DB::table('osu_user_donations')->insert([
                'user_id' => $target->getKey(),
                'transaction_id' => 'panel-'.Str::uuid(),
                'target_user_id' => $target->getKey(),
                'length' => $months,
                'amount' => 0,
                'cancel' => 0,
            ]);
            $expiry = $target->osu_subscriptionexpiry !== null && $target->osu_subscriptionexpiry->isFuture()
                ? $target->osu_subscriptionexpiry
                : Carbon::now();
            $target->update(['osu_subscriber' => true, 'osu_subscriptionexpiry' => $expiry->copy()->addMonthsNoOverflow($months)]);
            $target->refreshSupportLength();
        });
        $target = $target->fresh();

        return $this->done($target, 'admin.supporter', "Gave {$target->username} {$months} month(s) of supporter.",
            "Added {$months} month(s): supporter until {$target->osu_subscriptionexpiry->toFormattedDateString()}, level {$target->supportLevel()}.");
    }

    public function delete(string $user)
    {
        $target = User::findOrFail((int) $user);
        if ($target->getKey() === Auth::user()->getKey() || $target->isAdmin()) {
            return $this->back($target, 'error', 'You can\'t delete your own account or another admin\'s.');
        }
        if (Request::input('confirm') !== $target->username) {
            return $this->back($target, 'error', 'Type the exact username to confirm.');
        }

        $old = $target->username;
        DB::transaction(function () use ($target) {
            foreach ($target->userGroups()->with('group')->get() as $membership) {
                if (!in_array($membership->group?->identifier, static::UNASSIGNABLE_GROUPS, true)) {
                    $target->removeFromGroup($membership->group, Auth::user());
                }
            }
            // osu-web treats DeletedUser_* as deleted; a banned account type keeps them out of the game.
            $name = "DeletedUser_{$target->getKey()}";
            $target->update([
                'username' => $name,
                'username_clean' => strtolower($name),
                'user_email' => null,
                'user_type' => 1,
                'osu_subscriber' => false,
            ]);
        });

        return $this->done($target->fresh(), 'admin.delete', "Deleted {$old}'s account.", "Deleted {$old}: renamed, banned from the game, removed from all groups. Their scores stay on record but hidden.");
    }

    private function done(User $target, string $action, string $summary, ?string $message = null)
    {
        $this->staffLog($action, $target->getKey(), $summary);
        $message ??= $summary;
        if (!$this->refreshInGame($target->getKey())) {
            $message .= ' The game server couldn\'t be reached, so it applies when they next log in.';
        }

        return $this->back($target, 'success', $message);
    }

    private function back(User $target, string $type, string $message)
    {
        return redirect(route('panel.admin.users.show', ['user' => $target->getKey()]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
