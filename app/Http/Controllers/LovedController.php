<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Libraries\TomYumLoved;
use App\Models\Beatmap;
use App\Models\TomYumLovedRequest;
use App\Transformers\BeatmapsetTransformer;
use App\Transformers\UserCompactTransformer;
use Auth;
use DB;
use Request;

/**
 * TomYum: Project Loved (Community → Project Loved): players ask for maps to be Loved and review each
 * other's requests; the Loved team loves, rejects, removes or blacklists (App\Libraries\TomYumLoved).
 */
class LovedController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['index', 'show', 'blacklist']]);

        parent::__construct();
    }

    /** The requests in one status (open: by score; the others: the latest decided first), for a mode or all. */
    public function index()
    {
        $user = Auth::user();
        $status = Request::input('status');
        if (!in_array($status, TomYumLovedRequest::STATUSES, true) || ($status === 'removed' && !TomYumLoved::isTeam($user))) {
            $status = 'open';
        }
        $ruleset = Request::input('mode');
        $rulesetId = is_string($ruleset) ? (Beatmap::MODES[$ruleset] ?? null) : null;
        if ($rulesetId === null) {
            $ruleset = null;
        }

        $requests = TomYumLovedRequest::where('status', $status)
            ->when($rulesetId !== null, fn ($query) => $query->where('ruleset_id', $rulesetId))
            ->with(['beatmapset.beatmaps', 'user', 'handler'])
            ->withCount([
                'reviews as reviews_for' => fn ($query) => $query->where('score', '>', 0),
                'reviews as reviews_against' => fn ($query) => $query->where('score', '<', 0),
            ])
            ->when(
                $status === 'open',
                fn ($query) => $query->orderByDesc('score')->orderBy('id'),
                fn ($query) => $query->orderByDesc('decided_at'),
            )
            ->paginate(30)
            ->withQueryString();

        return ext_view('loved.index', [
            'counts' => TomYumLovedRequest::where('status', $status)->groupBy('ruleset_id')->selectRaw('ruleset_id, count(*) c')->pluck('c', 'ruleset_id'),
            'items' => collect($requests->items())->filter(fn ($request) => $request->beatmapset !== null)->map(fn ($request) => [
                'beatmapsetJson' => json_item($request->beatmapset, new BeatmapsetTransformer(), ['beatmaps']),
                'request' => $request,
            ])->values(),
            'requests' => $requests,
            'ruleset' => $ruleset,
            'status' => $status,
            'team' => $status === 'open' ? static::team() : [],
        ]);
    }

    /** One request: the map, why it was asked for, the reviews, and the team's decision. */
    public function show(string $request)
    {
        $request = TomYumLovedRequest::with(['beatmapset.beatmaps', 'user', 'handler', 'reviews.user'])->findOrFail((int) $request);
        $user = Auth::user();
        if ($request->status === 'removed' && !TomYumLoved::isTeam($user)) {
            abort(404);
        }

        return ext_view('loved.show', [
            'beatmapsetJson' => $request->beatmapset === null ? null : json_item($request->beatmapset, new BeatmapsetTransformer(), ['beatmaps']),
            'blacklisted' => DB::table('tomyum_loved_blacklist')->where('beatmapset_id', $request->beatmapset_id)->first(),
            'canDecide' => TomYumLoved::canDecide($user, $request),
            'mine' => $request->reviewBy($user),
            'request' => $request,
            'reviews' => $request->reviews->sortBy('created_at')->values(),
        ]);
    }

    public function storeRequest()
    {
        $user = Auth::user();
        $old = Request::only('beatmapset', 'mode', 'reason');
        if (($why = TomYumLoved::cannotTakePart($user)) !== null) {
            return $this->backToList('error', $why, $old);
        }
        [$ok, $message, $request] = TomYumLoved::request(
            $user,
            (string) Request::input('beatmapset'),
            get_int(Request::input('mode')) ?? -1,
            (string) Request::input('reason'),
        );

        if ($request !== null) {
            return $this->backToRequest($request, $ok ? 'success' : 'error', $message);
        }

        return $this->backToList('error', $message, $old);
    }

    public function review(string $request)
    {
        $request = TomYumLovedRequest::findOrFail((int) $request);
        $user = Auth::user();
        if (($why = TomYumLoved::cannotTakePart($user)) !== null) {
            return $this->backToRequest($request, 'error', $why);
        }
        [$ok, $message] = TomYumLoved::review($user, $request, get_int(Request::input('score')) ?? 0, (string) Request::input('comment'));

        return $this->backToRequest($request, $ok ? 'success' : 'error', $message, $ok ? null : Request::only('score', 'comment'));
    }

    public function destroyReview(string $request)
    {
        $request = TomYumLovedRequest::findOrFail((int) $request);
        TomYumLoved::deleteReview(Auth::user(), $request);

        return $this->backToRequest($request, 'success', 'Your review is removed.');
    }

    public function decide(string $request)
    {
        $request = TomYumLovedRequest::with('beatmapset')->findOrFail((int) $request);
        $user = Auth::user();
        if (!TomYumLoved::canDecide($user, $request)) {
            abort(403);
        }
        [$ok, $message] = TomYumLoved::decide($user, $request, (string) Request::input('action'), (string) Request::input('reason'));

        return $this->backToRequest($request, $ok ? 'success' : 'error', $message, $ok ? null : Request::only('reason'));
    }

    /** The blacklist: maps nobody can ask for, and why. */
    public function blacklist()
    {
        $entries = DB::table('tomyum_loved_blacklist')->orderByDesc('created_at')->get();
        $sets = \App\Models\Beatmapset::whereIn('beatmapset_id', $entries->pluck('beatmapset_id'))->get()->keyBy('beatmapset_id');
        $users = \App\Models\User::whereIn('user_id', $entries->pluck('user_id'))->get()->keyBy('user_id');

        return ext_view('loved.blacklist', [
            'entries' => $entries,
            'isTeam' => TomYumLoved::isTeam(Auth::user()),
            'sets' => $sets,
            'users' => $users,
        ]);
    }

    public function storeBlacklist()
    {
        $user = Auth::user();
        if (!TomYumLoved::isTeam($user)) {
            abort(403);
        }
        [$ok, $message] = TomYumLoved::blacklist($user, (string) Request::input('beatmapset'), (string) Request::input('reason'));

        $redirect = redirect(route('loved.blacklist'))->with('loved_status', ['type' => $ok ? 'success' : 'error', 'message' => $message]);

        return $ok ? $redirect : $redirect->withInput(Request::only('beatmapset', 'reason'));
    }

    public function destroyBlacklist(string $beatmapset)
    {
        if (!TomYumLoved::isTeam(Auth::user())) {
            abort(403);
        }
        DB::table('tomyum_loved_blacklist')->where('beatmapset_id', (int) $beatmapset)->delete();

        return redirect(route('loved.blacklist'))->with('loved_status', ['type' => 'success', 'message' => 'The map is off the blacklist.']);
    }

    /** The Project Loved group's members as osu-web's user cards want them, by name. */
    private static function team(): array
    {
        $group = app('groups')->byIdentifier('loved');
        if ($group === null) {
            return [];
        }
        $users = $group->users()
            ->default()
            ->with(UserCompactTransformer::CARD_INCLUDES_PRELOAD)
            ->orderBy('username')
            ->get();

        return json_collection($users, new UserCompactTransformer(), UserCompactTransformer::CARD_INCLUDES);
    }

    private function backToList(string $type, string $message, ?array $old = null)
    {
        $redirect = redirect(route('loved.index', ['mode' => Beatmap::modeStr(get_int(Request::input('mode')) ?? -1)]).'#ask')
            ->with('loved_status', ['type' => $type, 'message' => $message]);

        return $old === null ? $redirect : $redirect->withInput($old);
    }

    private function backToRequest(TomYumLovedRequest $request, string $type, string $message, ?array $old = null)
    {
        $redirect = redirect(route('loved.requests.show', ['request' => $request->getKey()]))
            ->with('loved_status', ['type' => $type, 'message' => $message]);

        return $old === null ? $redirect : $redirect->withInput($old);
    }
}
