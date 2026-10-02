<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Libraries\TomYumLoved;
use App\Models\Beatmap;
use App\Models\TomYumLovedNomination;
use App\Models\TomYumLovedRequest;
use App\Models\TomYumLovedRound;
use App\Transformers\BeatmapsetTransformer;
use App\Transformers\UserCompactTransformer;
use Auth;
use DB;
use Request;

/**
 * TomYum: the public Project Loved page (Community → Project Loved): a round's maps by mode, with
 * their polls; voting here votes in the map's forum topic poll.
 */
class LovedController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['only' => ['storeRequest', 'support', 'vote']]);

        parent::__construct();
    }

    /** The overview: what Project Loved is, the latest round in short, past rounds, top requests, the team. */
    public function index()
    {
        // Links from before rounds had their own pages.
        $roundId = get_int(Request::input('round'));
        if ($roundId !== null) {
            return redirect(route('loved.rounds.show', ['round' => $roundId, 'mode' => Request::input('mode')]));
        }

        $rounds = TomYumLovedRound::public()
            ->withCount(['nominations', 'nominations as loved_count' => fn ($query) => $query->where('passed', true)])
            ->orderByDesc('id')
            ->get();
        $latest = $rounds->first();
        $latestNominations = $latest?->nominations()->with('beatmapset')->orderBy('ruleset_id')->orderBy('id')->get() ?? collect();
        if ($latest?->isClosed()) {
            $latestNominations = $latestNominations->sortBy(fn ($nomination) => $nomination->passed ? 0 : 1)->values();
        }

        return ext_view('loved.index', [
            'latest' => $latest,
            'latestNominations' => $latestNominations->filter(fn ($nomination) => $nomination->beatmapset !== null)->values(),
            'rounds' => $rounds,
            'team' => static::team(),
            'topRequests' => TomYumLovedRequest::where('status', 'open')
                ->with('beatmapset')
                ->orderByDesc('supporters_count')
                ->orderBy('id')
                ->limit(5)
                ->get()
                ->filter(fn ($request) => $request->beatmapset !== null),
        ]);
    }

    /** A round's maps in one mode, with their polls. */
    public function round(string $round)
    {
        $rounds = TomYumLovedRound::public()->orderByDesc('id')->get();
        $round = $rounds->firstWhere('id', get_int($round)) ?? abort(404);

        $nominations = $round->nominations()->with(['beatmapset.beatmaps', 'beatmapset.user', 'captain', 'topic.pollOptions'])->orderBy('id')->get();
        $counts = $nominations->countBy('ruleset_id');

        $ruleset = Request::input('mode');
        if (!isset(Beatmap::MODES[$ruleset])) {
            // The first mode with maps in the round.
            $ruleset = array_search($counts->keys()->sort()->first() ?? 0, Beatmap::MODES, true);
        }
        $rulesetId = Beatmap::MODES[$ruleset];

        $user = Auth::user();
        $shown = $nominations->where('ruleset_id', $rulesetId)->values()->map(function (TomYumLovedNomination $nomination) use ($round, $user) {
            [$yes, $no] = TomYumLoved::votes($nomination);
            $topic = $nomination->topic;
            $canVote = $topic === null ? null : priv_check('ForumTopicVote', $topic);

            return [
                // For osu-web's beatmap card (data-react="beatmapset-panel"), as the rankings show them.
                'beatmapsetJson' => $nomination->beatmapset === null ? null
                    : json_item($nomination->beatmapset, new BeatmapsetTransformer(), ['beatmaps']),
                'nomination' => $nomination,
                'yes' => $yes,
                'no' => $no,
                'passes' => TomYumLoved::passes($round, $yes, $no),
                'votedFor' => $user === null || $topic === null ? null
                    : get_int($topic->pollVotes()->where('vote_user_id', $user->getKey())->value('poll_option_id')),
                // Why the player can't vote here, if they can't (osu-web's message for the poll).
                'cannotVote' => $canVote === null || $canVote->can() ? null : $canVote->message(),
            ];
        });
        // A closed round's results: the Loved maps first.
        if ($round->isClosed()) {
            $shown = $shown->sortBy(fn ($item) => $item['nomination']->passed ? 0 : 1)->values();
        }

        return ext_view('loved.round', [
            'counts' => $counts,
            'lovedCount' => $nominations->where('passed', true)->count(),
            'nominations' => $shown,
            'round' => $round,
            'ruleset' => $ruleset,
        ]);
    }

    /** The Project Loved group's members as osu-web's user cards want them (their group badge shows their modes), by name. */
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

    /** Requests: the open ones for a mode, the most supported first; then the ones declined lately. */
    public function requests()
    {
        $ruleset = Request::input('mode');
        if (!isset(Beatmap::MODES[$ruleset])) {
            $ruleset = default_mode(); // the player's own
        }
        $rulesetId = Beatmap::MODES[$ruleset];
        $user = Auth::user();

        $open = TomYumLovedRequest::where(['status' => 'open', 'ruleset_id' => $rulesetId])
            ->with(['beatmapset.beatmaps', 'user'])
            ->orderByDesc('supporters_count')
            ->orderBy('id')
            ->limit(100)
            ->get();
        $supported = $user === null ? [] : DB::table('tomyum_loved_request_supporters')
            ->where('user_id', $user->getKey())
            ->whereIn('request_id', $open->modelKeys())
            ->pluck('request_id')
            ->flip()
            ->all();
        $declined = TomYumLovedRequest::where(['status' => 'declined', 'ruleset_id' => $rulesetId])
            ->where('updated_at', '>', now()->subDays(90))
            ->with(['beatmapset', 'handler'])
            ->latest('updated_at')
            ->limit(20)
            ->get();

        return ext_view('loved.requests', [
            'counts' => TomYumLovedRequest::where('status', 'open')->groupBy('ruleset_id')->selectRaw('ruleset_id, count(*) c')->pluck('c', 'ruleset_id'),
            'declined' => $declined,
            'open' => $open->filter(fn ($request) => $request->beatmapset !== null)->map(fn ($request) => [
                'beatmapsetJson' => json_item($request->beatmapset, new BeatmapsetTransformer(), ['beatmaps']),
                'request' => $request,
                'supported' => isset($supported[$request->getKey()]),
            ])->values(),
            'round' => TomYumLovedRound::where('state', 'voting')->latest('id')->first(),
            'ruleset' => $ruleset,
        ]);
    }

    public function storeRequest()
    {
        $user = Auth::user();
        if ($user->isRestricted() || $user->isSilenced()) {
            return $this->backToRequests(null, 'error', "You can't ask for maps while restricted or silenced.");
        }
        [$ok, $message, $request] = TomYumLoved::request(
            $user,
            (string) Request::input('beatmapset'),
            get_int(Request::input('mode')) ?? -1,
            (string) Request::input('reason'),
        );

        return $this->backToRequests($request, $ok ? 'success' : 'error', $message, $ok ? null : Request::only('beatmapset', 'mode', 'reason'));
    }

    public function support(string $request)
    {
        $request = TomYumLovedRequest::findOrFail((int) $request);
        $user = Auth::user();
        if (!$request->isOpen()) {
            return $this->backToRequests($request, 'error', 'That request was handled already.');
        }
        if ($user->isRestricted()) {
            abort(403);
        }
        $support = Request::input('support') === '1';
        $request->setSupport($user, $support);

        return $this->backToRequests($request->exists ? $request : null, 'success', $support
            ? 'You support this request.'
            : ($request->exists ? 'You took your support back.' : 'You took your support back; nobody else supported it, so the request is gone.'));
    }

    private function backToRequests(?TomYumLovedRequest $request, string $type, string $message, ?array $old = null)
    {
        $url = route('loved.requests', ['mode' => Beatmap::modeStr($request?->ruleset_id ?? get_int(Request::input('mode')) ?? 0) ?? 'osu']);
        if ($request !== null) {
            $url .= '#request-'.$request->getKey();
        }
        $redirect = redirect($url)->with('loved_status', ['type' => $type, 'message' => $message]);

        return $old === null ? $redirect : $redirect->withInput($old);
    }

    public function vote(string $nomination)
    {
        $nomination = TomYumLovedNomination::with('topic')->findOrFail((int) $nomination);
        $topic = $nomination->topic;
        if ($topic === null || !$nomination->round->isVoting()) {
            abort(404);
        }
        // Also for admins, whom osu-web lets vote on any poll.
        if (!$topic->poll()->isOpen()) {
            return error_popup('Voting on this map has ended.');
        }

        priv_check('ForumTopicVote', $topic)->ensureCan();

        $option = Request::input('vote') === 'yes' ? TomYumLoved::OPTION_YES : TomYumLoved::OPTION_NO;
        $vote = $topic->vote()->fill([
            'option_ids' => [$option],
            'user_id' => Auth::user()->getKey(),
            'ip' => Request::ip(),
        ]);
        if (!$vote->save()) {
            return error_popup($vote->validationErrors()->toSentence());
        }

        return redirect(route('loved.rounds.show', [
            'round' => $nomination->round_id,
            'mode' => Beatmap::modeStr($nomination->ruleset_id),
        ]).'#nomination-'.$nomination->getKey());
    }
}
