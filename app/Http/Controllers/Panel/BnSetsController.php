<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Models\Beatmap;
use App\Models\Beatmapset;
use App\Models\Event;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Request;

/**
 * BN panel: find beatmap sets on the server and manage them (ranked status, refresh from bancho).
 * Statuses are changed on TomYum, which freezes them, applies them in-game and mirrors them here.
 */
class BnSetsController extends Controller
{
    /** Statuses staff can set (TomYum's names) with labels. */
    public const SETTABLE_STATUSES = [
        'ranked' => 'Ranked',
        'approved' => 'Approved',
        'qualified' => 'Qualified',
        'loved' => 'Loved',
        'pending' => 'Pending (unranked)',
        'bancho' => "Back to bancho (osu!'s own status)",
    ];

    /** osu-web `approved` value => label. */
    public const APPROVED_LABELS = [
        -2 => 'Graveyard',
        -1 => 'WIP',
        0 => 'Pending',
        1 => 'Ranked',
        2 => 'Approved',
        3 => 'Qualified',
        4 => 'Loved',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseBn(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $query = trim(Request::input('q') ?? '');
        $status = Request::input('status');

        $sets = Beatmapset::query()->orderByDesc('last_update');
        if ($query !== '') {
            $setId = BnController::parseSetId($query);
            if ($setId !== null) {
                $sets->where('beatmapset_id', $setId);
            } else {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
                $sets->where(fn ($q) => $q->where('title', 'like', $like)
                    ->orWhere('artist', 'like', $like)
                    ->orWhere('creator', 'like', $like));
            }
        }
        if ($status !== null && $status !== '' && array_key_exists((int) $status, static::APPROVED_LABELS)) {
            $sets->where('approved', (int) $status);
        }

        $page = $sets->paginate(50)->withQueryString();
        // Beatmapset only allows known attributes, so no withCount(): count difficulties separately.
        $difficultyCounts = Beatmap::whereIn('beatmapset_id', $page->pluck('beatmapset_id'))
            ->selectRaw('beatmapset_id, count(*) as difficulties')
            ->groupBy('beatmapset_id')
            ->pluck('difficulties', 'beatmapset_id');

        return ext_view('panel.bn.sets.index', [
            'sets' => $page,
            'difficultyCounts' => $difficultyCounts,
            'query' => $query,
            'status' => $status,
        ]);
    }

    public function show(string $set)
    {
        $setId = (int) $set;
        $beatmapset = Beatmapset::with('beatmaps')->find($setId);
        $server = $this->tomyum()->get("/beatmapsets/{$setId}");
        $log = $this->tomyum()->get('/log', ['targetId' => $setId, 'action' => 'beatmapset.', 'limit' => 50]);

        if ($beatmapset === null && (!$server->successful() || count($server->json()) === 0)) {
            abort(404);
        }

        $entries = $log->successful() ? $log->json() : [];

        return ext_view('panel.bn.sets.show', [
            'setId' => $setId,
            'beatmapset' => $beatmapset,
            // TomYum: who nominated it (the site's nominations), and whether this user may set them.
            'nominators' => $beatmapset === null ? collect() : User::whereIn(
                'user_id',
                $beatmapset->beatmapsetNominations()->current()->select('user_id'),
            )->orderBy('username')->get(['user_id', 'username']),
            'canSetNominators' => static::canUseAdmin(Auth::user()),
            'difficulties' => $server->successful() ? $server->json() : [],
            'serverError' => $server->successful() ? null : "Couldn't load the game server's copy (HTTP {$server->status()}).",
            'entries' => $entries,
            'actors' => BnController::actorNames($entries),
        ]);
    }

    public function status(string $set)
    {
        $setId = (int) $set;
        $status = Request::input('status');
        if (!array_key_exists($status, static::SETTABLE_STATUSES)) {
            return $this->back($setId, 'error', 'Pick a status.');
        }

        $beatmapIds = array_values(array_map('intval', Request::input('beatmaps') ?? []));
        $response = $this->tomyum()->post("/beatmapsets/{$setId}/status", [
            'status' => $status,
            'beatmapIds' => $beatmapIds,
        ]);
        if (!$response->successful()) {
            return $this->back($setId, 'error', $response->json('error') ?? "Changing the status failed (HTTP {$response->status()}).");
        }

        $changed = $response->json('changed');
        // Back to pending (or to bancho's status): the nominations so far no longer count, as when
        // osu! disqualifies or unranks a set; otherwise the BNs couldn't nominate it again.
        if (in_array($status, ['pending', 'bancho'], true) && count($beatmapIds) === 0) {
            $beatmapset = Beatmapset::find($setId);
            if ($beatmapset !== null && $beatmapset->beatmapsetNominations()->current()->exists()) {
                $beatmapset->beatmapsetNominations()->current()->update(['reset' => true, 'reset_at' => now(), 'reset_user_id' => Auth::user()->getKey()]);
                $beatmapset->refreshCache();
            }
        }
        if ($status === 'bancho') {
            $message = count($changed) === 0
                ? "Nothing to unlock: no staff-set status there (or osu! doesn't know the set)."
                : count($changed).' difficult'.(count($changed) === 1 ? 'y is' : 'ies are')." back to bancho's status: ".implode(', ', $changed).'.';
        } else {
            $label = static::SETTABLE_STATUSES[$status];
            $message = count($beatmapIds) === 0
                ? "The whole set is now {$label}."
                : count($changed).' difficult'.(count($changed) === 1 ? 'y is' : 'ies are')." now {$label}: ".implode(', ', $changed).'.';
        }
        $recalculated = $response->json('scores.changed') ?? 0;
        if ($recalculated > 0) {
            $players = $response->json('scores.players') ?? 0;
            $message .= " Recalculated {$recalculated} score(s) of {$players} player(s).";
        }
        if (in_array($status, ['ranked', 'approved', 'qualified', 'loved'], true) && count($changed) > 0) {
            $this->announce($setId, $status);
        }

        return $this->back($setId, 'success', $message);
    }

    /**
     * The "… by mapper has just been ranked!" entry in the site's activity (Community → Activity, and
     * the mapper's profile when they play here), as osu! writes it. Written here rather than with
     * Event::generate, which needs the mapper to have an account: most imported sets' don't.
     */
    private function announce(int $setId, string $status): void
    {
        $set = Beatmapset::find($setId);
        if ($set === null) {
            return;
        }

        $mapper = User::find($set->user_id);
        $name = $mapper?->username ?? $set->creator;
        $setUrl = route('beatmapsets.show', ['beatmapset' => $setId], false);
        // A mapper without an account here (an imported set's) links to their osu! profile.
        $userUrl = match (true) {
            $mapper !== null => route('users.show', ['user' => $mapper->getKey()], false),
            $set->user_id > 0 => "https://osu.ppy.sh/users/{$set->user_id}",
            default => 'https://osu.ppy.sh/users/'.rawurlencode($name),
        };
        $title = "{$set->artist} - {$set->title}";
        $site = $GLOBALS['cfg']['app']['url'];

        Event::create([
            'text' => sprintf(
                "<a href='%s'>%s</a> by <b><a href='%s'>%s</a></b> has just been %s!",
                e($setUrl),
                e($title),
                e($userUrl),
                e($name),
                $status,
            ),
            'text_clean' => "[{$site}{$setUrl} {$title}] by [".(is_http($userUrl) ? $userUrl : $site.$userUrl)." {$name}] has just been {$status}!",
            'beatmap_id' => 0,
            'beatmapset_id' => $setId,
            'user_id' => $mapper?->getKey(),
            'private' => false,
            'epicfactor' => 8,
            'date' => Carbon::now(),
        ]);
    }

    public function refresh(string $set)
    {
        $setId = (int) $set;
        $response = $this->tomyum()->post("/beatmapsets/{$setId}/import");
        if ($response->notFound()) {
            return $this->back($setId, 'error', "osu! doesn't have this set (anymore), so there's nothing to refresh from.");
        }
        if (!$response->successful()) {
            return $this->back($setId, 'error', "Refresh failed (HTTP {$response->status()}).");
        }

        return $this->back($setId, 'success', "Refreshed from bancho: {$response->json('difficulties')} difficulties. Statuses set by staff were kept.");
    }

    /**
     * TomYum: admins set who nominated a set by hand (a set they ranked themselves, a bancho map that
     * wasn't ranked there): its current nominations are reset and these players' are recorded, as
     * nominations made on the discussion page are (an event each, in the set's history), so the
     * beatmap page shows them under "Nominated by". It doesn't change the set's status.
     */
    public function nominators(string $set)
    {
        $this->ensure(static::canUseAdmin(Auth::user()));

        $setId = (int) $set;
        $beatmapset = Beatmapset::find($setId);
        if ($beatmapset === null) {
            return $this->back($setId, 'error', "The website doesn't have this set yet.");
        }

        $names = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\n]/', (string) Request::input('nominators'))))));
        if (count($names) > 10) {
            return $this->back($setId, 'error', 'Ten nominators at most.');
        }

        $users = [];
        $missing = [];
        foreach ($names as $name) {
            $user = ctype_digit($name) ? User::find((int) $name) : User::lookup($name, 'username');
            if ($user === null) {
                $missing[] = $name;
            } else {
                $users[$user->getKey()] = $user;
            }
        }
        if (count($missing) > 0) {
            return $this->back($setId, 'error', 'No player called '.implode(', ', array_map(fn ($name) => "\"{$name}\"", $missing)).'.');
        }

        $ruleset = $beatmapset->mainRuleset() ?? Beatmap::modeStr($beatmapset->beatmaps()->value('playmode') ?? 0) ?? 'osu';
        $admin = Auth::user();
        $beatmapset->getConnection()->transaction(function () use ($beatmapset, $users, $ruleset, $admin) {
            $beatmapset->beatmapsetNominations()->current()->update([
                'reset' => true,
                'reset_at' => now(),
                'reset_user_id' => $admin->getKey(),
            ]);

            foreach ($users as $user) {
                $event = $beatmapset->events()->create([
                    'comment' => ['modes' => [$ruleset]],
                    'type' => \App\Models\BeatmapsetEvent::NOMINATE,
                    'user_id' => $user->getKey(),
                ]);
                $beatmapset->beatmapsetNominations()->create([
                    'event_id' => $event->getKey(),
                    'modes' => [$ruleset => 'full'],
                    'user_id' => $user->getKey(),
                ]);
            }

            $beatmapset->refreshCache(true);
        });

        // Enough of them qualify a pending set, as the last BN nomination does (from here no hype or
        // open issues are asked for: an admin decided). qualify() passes the status on to TomYum.
        $beatmapset = $beatmapset->fresh();
        $required = $GLOBALS['cfg']['osu']['beatmapset']['required_nominations'];
        $qualified = false;
        // A graveyard or WIP set (as bancho has it) counts as pending here: it's pending first.
        $unranked = $beatmapset->isPending() || in_array($beatmapset->approved, [Beatmapset::STATES['graveyard'], Beatmapset::STATES['wip']], true);
        if ($unranked && count($users) >= $required) {
            try {
                if (!$beatmapset->isPending()) {
                    $beatmapset->setApproved('pending', $admin);
                    $beatmapset = $beatmapset->fresh();
                }
                $beatmapset->qualify($admin);
                $qualified = true;
            } catch (\Throwable $e) {
                log_error($e);
            }
        }

        $list = implode(', ', array_map(fn ($user) => $user->username, $users));
        $summary = count($users) === 0 ? 'Cleared the nominators' : "Set the nominators to {$list}";
        $this->staffLog('beatmapset.nominators', $setId, "{$summary} (set {$setId})", $qualified ? 'qualified' : 'nominators');

        if (count($users) === 0) {
            return $this->back($setId, 'success', 'Nobody is shown as nominating it now.');
        }

        $message = "Nominated by {$list} now.";
        if ($qualified) {
            $message .= ' That qualified it: it ranks after the qualified period.';
        } elseif ($beatmapset->isPending()) {
            $message .= " It needs {$required} to qualify.";
        }

        return $this->back($setId, 'success', $message);
    }

    private function back(int $setId, string $type, string $message)
    {
        return redirect(route('panel.bn.sets.show', ['set' => $setId]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
