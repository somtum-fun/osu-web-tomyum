<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Exceptions\ChangeUsernameException;
use App\Libraries\TomYumAccount;
use App\Libraries\TomYumCountryStats;
use App\Libraries\User\AvatarHelper;
use App\Libraries\User\PasswordHelper;
use App\Models\Beatmap;
use App\Models\Country;
use App\Models\Group;
use App\Models\User;
use App\Models\UserAccountHistory;
use App\Models\UsernameChangeHistory;
use App\Models\UserStatistics;
use App\Transformers\UserCompactTransformer;
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

        $devices = $this->tomyum()->get("/users/{$target->getKey()}/devices");
        $links = $this->tomyum()->get("/users/{$target->getKey()}/links");
        $anticheat = $this->tomyum()->get('/log', ['targetId' => $target->getKey(), 'limit' => 200]);

        return ext_view('panel.admin.user', [
            'target' => $target,
            'isSelf' => $target->getKey() === Auth::user()->getKey(),
            // The header: osu-web's user card, and their main mode's numbers.
            'userCard' => json_item($target, new UserCompactTransformer(), UserCompactTransformer::CARD_INCLUDES),
            'stats' => $target->statistics($target->playmode ?? 'osu'),
            // Moderation (TomYum): flags, standing, devices, logins, links, anti-cheat, names.
            'flags' => TomYumAccount::flags($target->getKey()),
            'silencedUntil' => GmtController::silencedUntil($target),
            'silenceLengths' => GmtController::SILENCE_LENGTHS,
            'modes' => GmtController::MODES,
            'history' => $target->accountHistories()->with('actor')->orderByDesc('timestamp')->orderByDesc('ban_id')->limit(50)->get(),
            'devices' => $devices->successful() ? $devices->json() : null,
            'verifiedDevices' => DB::table('osu_user_security')->where('user_id', $target->getKey())->get()
                ->mapWithKeys(fn ($row) => [bin2hex($row->unique_md5) => (bool) $row->verified])->all(),
            'links' => $links->successful() ? $links->json() : null,
            'logins' => TomYumAccount::loginHistory($target->getKey(), 50),
            'lastDetection' => collect($anticheat->successful() ? $anticheat->json() : [])->first(fn ($entry) =>
                str_starts_with($entry['action'], 'anticheat.')
                || $entry['action'] === 'user.client_flags'
                || ($entry['action'] === 'user.restrict' && in_array($entry['result'] ?? null, ['circleguard', 'client flags'], true))),
            'usernames' => UsernameChangeHistory::where('user_id', $target->getKey())->orderByDesc('change_id')->limit(30)->get(),
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
            $oldCountry = $target->country_acronym;
            $target->update(['country_acronym' => $country]);
            // Rankings filter on the copy of the country in each ruleset's stats row.
            foreach (array_keys(Beatmap::MODES) as $modeStr) {
                UserStatistics\Model::getClass($modeStr)::where('user_id', $target->getKey())->update(['country_acronym' => $country]);
            }
            TomYumCountryStats::recalculate($oldCountry);
            TomYumCountryStats::recalculate($country);
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

        TomYumCountryStats::recalculate($target->country_acronym);

        return $this->done($target->fresh(), 'admin.delete', "Deleted {$old}'s account.", "Deleted {$old}: renamed, banned from the game, removed from all groups. Their scores stay on record but hidden.");
    }

    /** Lock: no logins anywhere (website, lazer, stable) and signed out everywhere; unlock undoes it. */
    public function lock(string $user)
    {
        $target = User::findOrFail((int) $user);
        $reason = $this->reason();
        if (Request::input('action') === 'unlock') {
            TomYumAccount::setLocked($target, Auth::user(), null);
            $this->history($target, 'note', 'Account unlocked'.($reason === null ? '' : ": {$reason}"));

            return $this->done($target, 'user.unlock', "Unlocked {$target->username}".($reason === null ? '.' : ": {$reason}"), 'Unlocked: they can log in again.');
        }
        if ($target->getKey() === Auth::user()->getKey()) {
            return $this->back($target, 'error', 'You can\'t lock your own account.');
        }
        if ($reason === null) {
            return $this->back($target, 'error', 'Give a reason for the lock.');
        }

        TomYumAccount::setLocked($target, Auth::user(), $reason);
        $target->resetSessions();
        $this->disconnect($target, 'Your account was locked by staff.');
        $this->history($target, 'note', "Account locked: {$reason}");

        return $this->done($target, 'user.lock', "Locked {$target->username}: {$reason}", 'Locked: signed out everywhere, and no logins until unlocked.');
    }

    /** The anti-cheat whitelist: circleguard and the suspicious-play checks leave the player alone. */
    public function whitelist(string $user)
    {
        $target = User::findOrFail((int) $user);
        $reason = $this->reason();
        if (Request::input('action') === 'remove') {
            TomYumAccount::setWhitelisted($target, Auth::user(), null);

            return $this->done($target, 'user.whitelist_remove', "Took {$target->username} off the anti-cheat whitelist.", 'Off the whitelist: anti-cheat checks them again.');
        }
        if ($reason === null) {
            return $this->back($target, 'error', 'Give a reason for whitelisting.');
        }
        TomYumAccount::setWhitelisted($target, Auth::user(), $reason);

        return $this->done($target, 'user.whitelist', "Whitelisted {$target->username} (anti-cheat): {$reason}", 'Whitelisted: anti-cheat leaves their plays alone.');
    }

    /** Wipes the player's device verifications: every stable device has to be confirmed on the website again. */
    public function wipeDevices(string $user)
    {
        $target = User::findOrFail((int) $user);
        $reason = $this->reason() ?? 'Devices reset by staff';
        $count = DB::table('osu_user_security')->where('user_id', $target->getKey())->update(['verified' => 0]);
        $this->disconnect($target, 'Your devices were reset by staff: verify this one on the website to play.');

        return $this->done($target, 'user.hwid_wipe', "Reset {$target->username}'s {$count} device verification(s): {$reason}",
            "Reset {$count} device(s): their next stable login asks them to verify the device.");
    }

    /** A note in the account's standing history. */
    public function note(string $user)
    {
        $target = User::findOrFail((int) $user);
        $reason = $this->reason();
        if ($reason === null) {
            return $this->back($target, 'error', 'Write the note.');
        }
        $this->history($target, 'note', $reason);

        return $this->done($target, 'user.note', "Note on {$target->username}: {$reason}", 'Note added.');
    }

    /** Resets the avatar, profile banner or me! page, with a note and/or a silence if chosen. */
    public function reset(string $user)
    {
        $target = User::findOrFail((int) $user);
        $what = Request::input('what');
        $labels = ['avatar' => 'avatar', 'cover' => 'profile banner', 'page' => 'me! page'];
        if (!isset($labels[$what])) {
            return $this->back($target, 'error', 'Pick what to reset.');
        }
        $reason = $this->reason() ?? 'inappropriate content';

        match ($what) {
            'avatar' => AvatarHelper::set($target, null),
            'cover' => (function () use ($target) {
                $target->cover()->set(null, null);
                $target->customCover()->delete();
                $target->saveOrExplode();
            })(),
            'page' => $target->userPage === null ? null : $target->updatePage(''),
        };

        $extra = [];
        if (Request::boolean('warn')) {
            $this->history($target, 'note', "Warned: {$labels[$what]} reset ({$reason})");
            $extra[] = 'warned';
        }
        $seconds = (int) Request::input('silence');
        if ($seconds > 0 && isset(GmtController::SILENCE_LENGTHS[$seconds])) {
            $this->history($target, 'silence', "{$labels[$what]} reset: {$reason}", $seconds);
            $extra[] = 'silenced for '.GmtController::SILENCE_LENGTHS[$seconds];
        }
        $tail = count($extra) > 0 ? ' ('.implode(', ', $extra).')' : '';

        return $this->done($target, 'user.reset_'.$what, "Reset {$target->username}'s {$labels[$what]}: {$reason}{$tail}", ucfirst($labels[$what])." reset{$tail}.");
    }

    /** Unlinks a connected profile (Discord, Last.fm). */
    public function unlink(string $user)
    {
        $target = User::findOrFail((int) $user);
        $service = Request::input('service');
        $response = match ($service) {
            'discord' => $this->tomyum()->post("/users/{$target->getKey()}/links/discord/unlink"),
            'lastfm' => $this->tomyum()->post("/lastfm/{$target->getKey()}/unlink"),
            default => null,
        };
        if ($response === null) {
            return $this->back($target, 'error', 'Unknown service.');
        }
        $name = $service === 'discord' ? 'Discord' : 'Last.fm';
        if ($response->notFound()) {
            return $this->back($target, 'error', "They have no {$name} linked.");
        }
        if (!$response->successful()) {
            return $this->back($target, 'error', "Unlinking failed (HTTP {$response->status()}).");
        }

        return $this->done($target, 'user.unlink_'.$service, "Unlinked {$target->username}'s {$name}.", "{$name} unlinked.");
    }

    /** Removes every score set after a moment (in one mode or all), recalculating their bests and stats. */
    public function rollback(string $user)
    {
        $target = User::findOrFail((int) $user);
        $reason = $this->reason();
        $mode = Request::input('mode');
        $mode = $mode === 'all' ? null : (int) $mode;
        try {
            $after = Carbon::parse((string) Request::input('after'), 'UTC');
        } catch (\Throwable) {
            $after = null;
        }
        if ($reason === null || $after === null || $after->isFuture() || ($mode !== null && !isset(GmtController::MODES[$mode]))) {
            return $this->back($target, 'error', 'Pick a date in the past and a mode, and give a reason.');
        }

        $response = $this->tomyum()->post("/users/{$target->getKey()}/wipe-scores", [
            'mode' => $mode,
            'reason' => $reason,
            'after' => $after->toIso8601String(),
        ]);
        if (!$response->successful()) {
            return $this->back($target, 'error', "The rollback failed (HTTP {$response->status()}).");
        }
        $which = $mode === null ? 'all modes' : GmtController::MODES[$mode];

        return $this->back($target, 'success', "Rolled back to {$after->toDayDateTimeString()} UTC: removed {$response->json('removed')} score(s) ({$which}); their earlier bests count again.");
    }

    /** Changes the name back to one from their username history, with a warning if chosen. */
    public function usernameRollback(string $user)
    {
        $target = User::findOrFail((int) $user);
        $name = trim(Request::input('username') ?? '');
        $known = UsernameChangeHistory::where('user_id', $target->getKey())
            ->where(fn ($q) => $q->where('username_last', $name)->orWhere('username', $name))->exists();
        if ($name === '' || !$known || $name === $target->username) {
            return $this->back($target, 'error', 'Pick one of their earlier names.');
        }

        $old = $target->username;
        try {
            $target->changeUsername($name, 'revert');
        } catch (ChangeUsernameException $e) {
            return $this->back($target, 'error', 'Username not changed: '.implode(' ', $e->getErrors()->allMessages()));
        }
        $reason = $this->reason();
        if (Request::boolean('warn')) {
            $this->history($target, 'note', "Warned: username reverted from {$old} to {$name}".($reason === null ? '' : " ({$reason})"));
        }

        return $this->done($target->fresh(), 'user.username_rollback', "Reverted {$old}'s name to {$name}".($reason === null ? '.' : ": {$reason}"),
            "Renamed back to {$name}".(Request::boolean('warn') ? ' (warned).' : '.'));
    }

    /** Recalculates every score's pp on the game server (after a pp update), which also updates this site. */
    public function recalculate()
    {
        $response = $this->tomyum()->post('/scores/recalculate');
        $status = $response->successful()
            ? ['type' => 'success', 'message' => "Recalculated pp on {$response->json('maps')} map(s): {$response->json('changed')} of {$response->json('scores')} score(s) changed."]
            : ['type' => 'error', 'message' => "Recalculating failed (HTTP {$response->status()})."];

        return redirect(route('panel.admin.index'))->with('panel_status', $status + ['link' => null]);
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

    private function reason(): ?string
    {
        $reason = trim(Request::input('reason') ?? '');

        return $reason === '' ? null : mb_substr($reason, 0, 500);
    }

    /** An entry in the account's standing history (osu_user_banhistory), by the current admin. */
    private function history(User $target, string $type, string $reason, int $period = 0): void
    {
        UserAccountHistory::create([
            'user_id' => $target->getKey(),
            'banner_id' => Auth::user()->getKey(),
            'ban_status' => UserAccountHistory::TYPES[$type],
            'reason' => mb_substr($reason, 0, 2000),
            'period' => $period,
            'permanent' => false,
            'timestamp' => Carbon::now(),
        ]);
    }

    /** Kicks their stable session (best effort), so a lock or a device reset applies now. */
    private function disconnect(User $target, string $message): void
    {
        try {
            $this->tomyum()->post("/users/{$target->getKey()}/disconnect", ['message' => $message]);
        } catch (\Throwable $e) {
            log_error($e);
        }
    }
}
