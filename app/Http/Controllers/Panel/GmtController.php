<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumAppeals;
use App\Libraries\TomYumCountryStats;
use App\Libraries\TomYumReports;
use App\Models\User;
use App\Models\UserAccountHistory;
use App\Models\UserReport;
use Auth;
use Carbon\Carbon;
use DB;
use Request;

/**
 * GMT panel: restrict/unrestrict, silence, reports, chat logs and score removal. Restrictions and
 * silences are stored the way osu-web models them (user_warnings, account history); TomYum is told
 * to apply them in-game right away and logs every action.
 */
class GmtController extends Controller
{
    /** Silence lengths offered in the panel, in seconds. */
    public const SILENCE_LENGTHS = [
        600 => '10 minutes',
        3600 => '1 hour',
        21600 => '6 hours',
        86400 => '1 day',
        259200 => '3 days',
        604800 => '1 week',
        2592000 => '30 days',
    ];

    /** TomYum game modes (vanilla 0-3, relax +4, autopilot +8). */
    public const MODES = [
        0 => 'osu!',
        1 => 'osu!taiko',
        2 => 'osu!catch',
        3 => 'osu!mania',
        4 => 'osu! relax',
        5 => 'osu!taiko relax',
        6 => 'osu!catch relax',
        8 => 'osu! autopilot',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseGmt(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $query = trim(Request::input('q') ?? '');
        $matches = [];
        if ($query !== '') {
            $exact = ctype_digit($query) ? User::find((int) $query) : User::lookup($query, 'username');
            if ($exact !== null) {
                return redirect(route('panel.gmt.users.show', ['user' => $exact->getKey()]));
            }
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
            $matches = User::where('username', 'like', $like)->orderBy('username')->limit(25)->get();
        }

        // The report queue: open, being handled, or resolved.
        $reportView = Request::input('reports');
        $reportView = isset(TomYumReports::VIEWS[$reportView]) ? $reportView : TomYumReports::OPEN;
        $reports = TomYumReports::list($reportView);
        $entries = $this->moderationLog();

        return ext_view('panel.gmt.index', [
            'query' => $query,
            'matches' => $matches,
            'reports' => $reports,
            'reportView' => $reportView,
            'reportCounts' => TomYumReports::counts(),
            'handlers' => static::userNames(array_filter($reports->map(fn ($r) => $r->tomyumState?->handler_id)->all())),
            'entries' => $entries,
            'actors' => BnController::actorNames($entries),
            'targets' => static::userNames(array_column($entries, 'targetId')),
        ]);
    }

    /** A report in the queue: take it, resolve it (with an optional note) or open it again. */
    public function reportState(string $report)
    {
        $report = UserReport::findOrFail((int) $report);
        $action = (string) Request::input('action');
        $note = presence(mb_substr(trim((string) Request::input('note')), 0, 500));
        $me = Auth::user()->getKey();
        [$status, $summary] = match ($action) {
            'take' => [TomYumReports::TAKEN, 'Took report #'.$report->getKey()],
            'resolve' => [TomYumReports::RESOLVED, 'Resolved report #'.$report->getKey().($note === null ? '' : ": {$note}")],
            'reopen' => [TomYumReports::OPEN, 'Reopened report #'.$report->getKey()],
            default => abort(422),
        };
        TomYumReports::set($report->getKey(), $status, $status === TomYumReports::OPEN ? null : $me, $note);
        $this->staffLog('report.'.$action, $report->user_id, $summary);

        $back = Request::input('back');

        return redirect(is_string($back) && str_starts_with($back, '/') ? $back : route('panel.gmt.index'));
    }

    public function show(string $user)
    {
        $target = User::findOrFail((int) $user);
        $best = Request::input('scores') === 'best';

        $scores = $this->tomyum()->get("/users/{$target->getKey()}/scores", ['best' => $best ? 'true' : 'false', 'limit' => 50]);
        $online = $this->tomyum()->get('/online');
        $log = $this->tomyum()->get('/log', ['targetId' => $target->getKey(), 'limit' => 100]);
        // The log is keyed by target id alone: keep the entries about users/scores, not beatmap sets.
        $entries = array_values(array_filter(
            $log->successful() ? $log->json() : [],
            fn ($entry) => str_starts_with($entry['action'], 'user.') || str_starts_with($entry['action'], 'score.'),
        ));

        return ext_view('panel.gmt.user', [
            'target' => $target,
            'userCard' => json_item($target, new \App\Transformers\UserCompactTransformer(), \App\Transformers\UserCompactTransformer::CARD_INCLUDES),
            'canModerate' => static::canModerate($target),
            'silencedUntil' => static::silencedUntil($target),
            'online' => $online->successful() && in_array($target->getKey(), array_column($online->json(), 'userId'), true),
            // TomYum: when they may appeal (restricted players only).
            'appealCooldown' => TomYumAppeals::currentCooldown($target),
            'appealAfter' => TomYumAppeals::appealAfter($target),
            'history' => $target->accountHistories()->with('actor')->orderByDesc('timestamp')->orderByDesc('ban_id')->limit(50)->get(),
            'reports' => TomYumReports::withStates(UserReport::with('reporter')->where('user_id', $target->getKey())->orderByDesc('report_id')->limit(30)->get()),
            'best' => $best,
            'scores' => $scores->successful() ? $scores->json() : [],
            'scoresError' => $scores->successful() ? null : "Couldn't load scores from the game server (HTTP {$scores->status()}).",
            'entries' => $entries,
            'actors' => BnController::actorNames($entries),
        ]);
    }

    public function restrict(string $user)
    {
        $target = $this->moderatable($user);
        $reason = $this->reason();
        if ($reason === null) {
            return $this->back($target, 'error', 'Give a reason.');
        }
        if ($target->isRestricted()) {
            return $this->back($target, 'error', 'Already restricted.');
        }

        // TomYum: the offense sets when they may appeal (the Restrictions & Appeals page's cooldowns).
        $offense = Request::input('offense');
        $offense = array_key_exists($offense, TomYumAppeals::OFFENSES) ? $offense : 'other';
        $appealAfter = static::appealDate();

        DB::transaction(function () use ($target, $reason, $offense, $appealAfter) {
            $target->update(['user_warnings' => 1]);
            $this->history($target, UserAccountHistory::TYPES['restriction'], $reason, 0, true);
            TomYumAppeals::restricted($target, $offense, Auth::user()->getKey(), $appealAfter, $reason);
        });
        $after = TomYumAppeals::appealAfter($target->fresh());

        return $this->done(
            $target,
            'user.restrict',
            "Restricted {$target->username} (".TomYumAppeals::OFFENSES[$offense]."): {$reason}",
            'Restricted. They can appeal from '.$after?->format('j M Y').'.',
        );
    }

    /**
     * TomYum: a restricted player's appeal cooldown: another offense (it starts again from today), or
     * a date set by hand.
     */
    public function appealCooldown(string $user)
    {
        $target = $this->moderatable($user);
        if (!$target->isRestricted()) {
            return $this->back($target, 'error', 'Not restricted.');
        }

        $offense = Request::input('offense');
        $appealAfter = static::appealDate();
        if (array_key_exists($offense, TomYumAppeals::OFFENSES)) {
            $reason = $this->reason();
            if ($reason === null) {
                return $this->back($target, 'error', 'Give a reason for the new offense.');
            }
            TomYumAppeals::restricted($target, $offense, Auth::user()->getKey(), $appealAfter, $reason);
            $this->history($target, UserAccountHistory::TYPES['note'], 'Another offense while restricted ('.TomYumAppeals::OFFENSES[$offense]."): {$reason}");
            $summary = "New offense for {$target->username} (".TomYumAppeals::OFFENSES[$offense]."): {$reason}";
        } elseif ($appealAfter !== null) {
            TomYumAppeals::setDate($target, $appealAfter, Auth::user()->getKey());
            $summary = "Appeal date for {$target->username} set to {$appealAfter->format('j M Y')}";
        } else {
            return $this->back($target, 'error', 'Pick an offense or a date.');
        }
        $this->staffLog('user.appeal-cooldown', $target->getKey(), $summary);

        return $this->back($target, 'success', 'They can appeal from '.TomYumAppeals::appealAfter($target)?->format('j M Y').'.');
    }

    public function unrestrict(string $user)
    {
        $target = $this->moderatable($user);
        $reason = $this->reason();
        if ($reason === null) {
            return $this->back($target, 'error', 'Give a reason.');
        }
        if (!$target->user_warnings) {
            return $this->back($target, 'error', 'Not restricted (a banned account type is changed from the admin panel).');
        }

        DB::transaction(function () use ($target, $reason) {
            $target->update(['user_warnings' => 0]);
            $this->history($target, UserAccountHistory::TYPES['note'], "Unrestricted: {$reason}");
        });

        return $this->done($target, 'user.unrestrict', "Unrestricted {$target->username}: {$reason}", 'Unrestricted.');
    }

    public function silence(string $user)
    {
        $target = $this->moderatable($user);
        $reason = $this->reason();
        $seconds = (int) Request::input('length');
        if ($reason === null || !array_key_exists($seconds, static::SILENCE_LENGTHS)) {
            return $this->back($target, 'error', 'Pick a length and give a reason.');
        }

        $this->history($target, UserAccountHistory::TYPES['silence'], $reason, $seconds);
        $length = static::SILENCE_LENGTHS[$seconds];

        return $this->done($target, 'user.silence', "Silenced {$target->username} for {$length}: {$reason}", "Silenced for {$length}.");
    }

    public function unsilence(string $user)
    {
        $target = $this->moderatable($user);
        if (static::silencedUntil($target) === null) {
            return $this->back($target, 'error', 'Not silenced.');
        }
        $reason = $this->reason() ?? 'Lifted early';

        // A silence with no length is newer than the running one, so it ends it (User::isSilenced).
        $this->history($target, UserAccountHistory::TYPES['silence'], "Silence lifted: {$reason}", 0);

        return $this->done($target, 'user.unsilence', "Lifted {$target->username}'s silence: {$reason}", 'Silence lifted.');
    }

    public function removeScore(string $user, string $score)
    {
        $target = $this->moderatable($user);
        $response = $this->tomyum()->delete('/scores/'.(int) $score, ['reason' => $this->reason() ?? '']);
        if ($response->notFound()) {
            return $this->back($target, 'error', 'That score is already gone.');
        }
        if (!$response->successful()) {
            return $this->back($target, 'error', "Removing the score failed (HTTP {$response->status()}).");
        }

        return $this->back($target, 'success', 'Score removed; their best on that map and their stats were recalculated.');
    }

    public function wipe(string $user)
    {
        $target = $this->moderatable($user);
        $reason = $this->reason();
        $mode = Request::input('mode');
        $mode = $mode === 'all' ? null : (int) $mode;
        if ($reason === null || ($mode !== null && !array_key_exists($mode, static::MODES))) {
            return $this->back($target, 'error', 'Pick a mode and give a reason.');
        }

        $response = $this->tomyum()->post("/users/{$target->getKey()}/wipe-scores", ['mode' => $mode, 'reason' => $reason]);
        if (!$response->successful()) {
            return $this->back($target, 'error', "The wipe failed (HTTP {$response->status()}).");
        }

        $which = $mode === null ? 'all modes' : static::MODES[$mode];

        return $this->back($target, 'success', "Wiped {$response->json('removed')} score(s) ({$which}).");
    }

    public function chat()
    {
        $userQuery = trim(Request::input('user') ?? '');
        $target = trim(Request::input('target') ?? '');
        $before = Request::input('before');

        $user = null;
        if ($userQuery !== '') {
            $user = ctype_digit($userQuery) ? User::find((int) $userQuery) : User::lookup($userQuery, 'username');
        }

        $params = array_filter([
            'userId' => $user?->getKey(),
            'target' => $target !== '' ? $target : null,
            'before' => $before,
            'limit' => 100,
        ], fn ($value) => $value !== null);
        $messages = ($userQuery !== '' && $user === null) ? null : $this->tomyum()->get('/chat', $params);

        return ext_view('panel.gmt.chat', [
            'userQuery' => $userQuery,
            'user' => $user,
            'target' => $target,
            'messages' => $messages?->successful() ? $messages->json() : [],
            'error' => match (true) {
                $messages === null => "No user called \"{$userQuery}\".",
                !$messages->successful() => "Couldn't load chat from the game server (HTTP {$messages->status()}).",
                default => null,
            },
        ]);
    }

    /** When $user's silence ends (osu-web's own rule), or null. */
    public static function silencedUntil(User $user): ?Carbon
    {
        $last = $user->accountHistories()->bans()->first();
        if ($last === null || $last->period === 0 || $last->ban_status !== UserAccountHistory::TYPES['silence']) {
            return null;
        }
        $end = $last->endTime();

        return $end->isFuture() ? $end : null;
    }

    /** user id => username. */
    public static function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return count($ids) === 0 ? [] : User::whereIn('user_id', $ids)->pluck('username', 'user_id')->all();
    }

    /** Recent restriction, silence and score actions, newest first. */
    private function moderationLog(): array
    {
        $entries = [];
        foreach (['user.', 'score.'] as $prefix) {
            $response = $this->tomyum()->get('/log', ['action' => $prefix, 'limit' => 30]);
            if ($response->successful()) {
                array_push($entries, ...$response->json());
            }
        }
        usort($entries, fn ($a, $b) => $b['id'] <=> $a['id']);

        return array_slice($entries, 0, 30);
    }

    private function moderatable(string $user): User
    {
        $target = User::findOrFail((int) $user);
        $this->ensure(static::canModerate($target));

        return $target;
    }

    /** TomYum: the appeal date picked by hand (a date in the future), else null. */
    private static function appealDate(): ?Carbon
    {
        $date = trim(Request::input('appeal_after') ?? '');
        if ($date === '') {
            return null;
        }
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $parsed->isFuture() ? $parsed : null;
    }

    private function reason(): ?string
    {
        $reason = trim(Request::input('reason') ?? '');

        return $reason === '' ? null : mb_substr($reason, 0, 2000);
    }

    private function history(User $target, int $type, string $reason, int $period = 0, bool $permanent = false): void
    {
        UserAccountHistory::create([
            'user_id' => $target->getKey(),
            'banner_id' => Auth::user()->getKey(),
            'ban_status' => $type,
            'reason' => $reason,
            'period' => $period,
            'permanent' => $permanent,
            'timestamp' => Carbon::now(),
        ]);
    }

    /** Logs the action, applies it in-game and goes back to the user page. */
    private function done(User $target, string $action, string $summary, string $message)
    {
        // Restricted players drop out of their country's ranking (and come back when unrestricted).
        if (in_array($action, ['user.restrict', 'user.unrestrict'], true)) {
            TomYumCountryStats::recalculate($target->country_acronym);
        }
        $this->staffLog($action, $target->getKey(), $summary);
        if (!$this->refreshInGame($target->getKey())) {
            $message .= " The game server couldn't be reached, so it applies when they next log in.";
        }

        return $this->back($target, 'success', $message);
    }

    private function back(User $target, string $type, string $message)
    {
        // TomYum: restrict and silence are on the admin panel's account page too; go back there.
        $page = Request::input('from') === 'admin' && static::canUseAdmin(Auth::user()) ? 'panel.admin.users.show' : 'panel.gmt.users.show';

        return redirect(route($page, ['user' => $target->getKey()]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
