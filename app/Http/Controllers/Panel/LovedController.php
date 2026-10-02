<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumLoved;
use App\Models\Beatmapset;
use App\Models\TomYumLovedNomination;
use App\Models\TomYumLovedRequest;
use App\Models\TomYumLovedRound;
use Auth;
use Request;

/**
 * The Loved panel (the Loved group and admins): Project Loved rounds. Captains add maps for their
 * modes while a round is being picked; opening the voting posts the polls, closing the round counts
 * them and loves what passed (App\Libraries\TomYumLoved).
 */
class LovedController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseLoved(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $rounds = TomYumLovedRound::withCount('nominations')->orderByDesc('id')->get();

        return ext_view('panel.loved.index', ['rounds' => $rounds]);
    }

    public function store()
    {
        $name = trim((string) Request::input('name'));
        if ($name === '' || mb_strlen($name) > 100) {
            return $this->back(null, 'error', 'A round needs a name of 1 to 100 characters.');
        }
        if (TomYumLovedRound::where('state', '!=', 'closed')->exists()) {
            return $this->back(null, 'error', 'Close the running round before starting another.');
        }

        $round = TomYumLovedRound::create([
            'name' => $name,
            ...$this->thresholds(),
        ]);
        $this->staffLog('loved.round', $round->getKey(), "Started the Project Loved round \"{$name}\"");

        return $this->back($round, 'success', "Started \"{$name}\". Add its maps below.");
    }

    public function show(string $round)
    {
        $round = TomYumLovedRound::findOrFail((int) $round);
        $nominations = $round->nominations()->with(['beatmapset', 'captain', 'topic.pollOptions'])->orderBy('ruleset_id')->orderBy('id')->get();

        $captainModes = TomYumLoved::captainModes(Auth::user());

        return ext_view('panel.loved.round', [
            'captainModes' => $captainModes,
            'nominations' => $nominations,
            // What players asked for in the captain's modes, the most supported first.
            'requests' => !$round->isPicking() ? collect() : TomYumLovedRequest::where('status', 'open')
                ->whereIn('ruleset_id', $captainModes)
                ->with(['beatmapset', 'user'])
                ->orderByDesc('supporters_count')
                ->orderBy('id')
                ->limit(50)
                ->get(),
            'round' => $round,
        ]);
    }

    public function update(string $round)
    {
        $round = TomYumLovedRound::findOrFail((int) $round);
        if ($round->isClosed()) {
            return $this->back($round, 'error', 'This round is closed.');
        }
        $name = trim((string) Request::input('name'));
        if ($name === '' || mb_strlen($name) > 100) {
            return $this->back($round, 'error', 'A round needs a name of 1 to 100 characters.');
        }
        $round->update(['name' => $name, ...$this->thresholds()]);
        $this->staffLog('loved.round', $round->getKey(), "Changed the Project Loved round \"{$name}\" (needs {$round->threshold}% of at least {$round->min_votes} votes)");

        return $this->back($round, 'success', 'Saved.');
    }

    public function destroy(string $round)
    {
        $round = TomYumLovedRound::findOrFail((int) $round);
        if (!$round->isPicking()) {
            return $this->back($round, 'error', 'Only a round still being picked can be removed: its polls are public.');
        }
        $round->nominations()->delete();
        $round->delete();
        $this->staffLog('loved.round', $round->getKey(), "Removed the Project Loved round \"{$round->name}\"");

        return $this->back(null, 'success', "Removed \"{$round->name}\".");
    }

    public function nominate(string $round)
    {
        $round = TomYumLovedRound::findOrFail((int) $round);
        if (!$round->isPicking()) {
            return $this->back($round, 'error', 'Maps can only be added while the round is being picked.');
        }

        $rulesetId = get_int(Request::input('mode'));
        if (!in_array($rulesetId, TomYumLoved::captainModes(Auth::user()), true)) {
            return $this->back($round, 'error', "You don't pick maps for that mode.");
        }

        $input = trim((string) Request::input('beatmapset'));
        $setId = preg_match('#beatmapsets/(\d+)#', $input, $match) ? (int) $match[1] : get_int($input);
        $set = $setId === null ? null : Beatmapset::find($setId);
        if ($set === null) {
            return $this->back($round, 'error', "That set isn't on the server. Import it in the BN panel first.");
        }
        if (!$set->isLoveable()) {
            return $this->back($round, 'error', "{$set->artist} - {$set->title} is {$set->status()} already.");
        }
        if (!$set->beatmaps()->where('playmode', $rulesetId)->exists()) {
            return $this->back($round, 'error', "{$set->artist} - {$set->title} has no ".TomYumLoved::MODE_NAMES[$rulesetId].' difficulties.');
        }
        if ($round->nominations()->where(['beatmapset_id' => $set->getKey(), 'ruleset_id' => $rulesetId])->exists()) {
            return $this->back($round, 'error', 'That map is in this round for that mode already.');
        }

        $nomination = $round->nominations()->create([
            'beatmapset_id' => $set->getKey(),
            'ruleset_id' => $rulesetId,
            'captain_id' => Auth::user()->getKey(),
            'description' => presence(trim((string) Request::input('description'))),
        ]);
        // A request for it is answered.
        TomYumLovedRequest::where(['beatmapset_id' => $set->getKey(), 'ruleset_id' => $rulesetId, 'status' => 'open'])
            ->first()
            ?->markPicked($nomination, Auth::user());
        $this->staffLog('loved.nominate', $set->getKey(), "Put {$set->artist} - {$set->title} in the Project Loved round \"{$round->name}\" (".TomYumLoved::MODE_NAMES[$rulesetId].')');

        return $this->back($round, 'success', "Added {$set->artist} - {$set->title}.", $nomination);
    }

    /** Adds a request's map to the round being picked. */
    public function pickRequest(string $round, string $request)
    {
        $round = TomYumLovedRound::findOrFail((int) $round);
        $request = TomYumLovedRequest::findOrFail((int) $request);
        if (!$request->isOpen()) {
            return $this->back($round, 'error', 'That request was handled already.');
        }
        Request::merge(['beatmapset' => (string) $request->beatmapset_id, 'mode' => (string) $request->ruleset_id]);

        return $this->nominate((string) $round->getKey());
    }

    public function declineRequest(string $round, string $request)
    {
        $round = TomYumLovedRound::findOrFail((int) $round);
        $request = TomYumLovedRequest::with('beatmapset')->findOrFail((int) $request);
        if (!$request->isOpen()) {
            return $this->back($round, 'error', 'That request was handled already.');
        }
        if (!in_array($request->ruleset_id, TomYumLoved::captainModes(Auth::user()), true)) {
            return $this->back($round, 'error', "You don't pick maps for that mode.");
        }
        $reason = trim((string) Request::input('reason'));
        if ($reason === '' || mb_strlen($reason) > 500) {
            return $this->back($round, 'error', 'Say why it was declined (up to 500 characters): players see it.');
        }
        $request->update(['status' => 'declined', 'decline_reason' => $reason, 'handled_by' => Auth::user()->getKey()]);
        $name = $request->beatmapset === null ? "set {$request->beatmapset_id}" : "{$request->beatmapset->artist} - {$request->beatmapset->title}";
        $this->staffLog('loved.decline', $request->beatmapset_id, "Declined the Loved request for {$name} (".TomYumLoved::MODE_NAMES[$request->ruleset_id]."): {$reason}");

        return $this->back($round, 'success', "Declined {$name}.");
    }

    public function updateNomination(string $nomination)
    {
        $nomination = TomYumLovedNomination::findOrFail((int) $nomination);
        $round = $nomination->round;
        if (!$round->isPicking()) {
            return $this->back($round, 'error', 'The write-up is in the poll topic now; edit it there.');
        }
        if (!in_array($nomination->ruleset_id, TomYumLoved::captainModes(Auth::user()), true)) {
            return $this->back($round, 'error', "You don't pick maps for that mode.");
        }
        $nomination->update(['description' => presence(trim((string) Request::input('description')))]);

        return $this->back($round, 'success', 'Saved the write-up.', $nomination);
    }

    public function destroyNomination(string $nomination)
    {
        $nomination = TomYumLovedNomination::with('beatmapset')->findOrFail((int) $nomination);
        $round = $nomination->round;
        if (!$round->isPicking()) {
            return $this->back($round, 'error', 'Voting has started: the map stays in the round.');
        }
        if (!in_array($nomination->ruleset_id, TomYumLoved::captainModes(Auth::user()), true)) {
            return $this->back($round, 'error', "You don't pick maps for that mode.");
        }
        $nomination->delete();
        $set = $nomination->beatmapset;
        $this->staffLog('loved.nominate', $nomination->beatmapset_id, 'Took '.($set === null ? "set {$nomination->beatmapset_id}" : "{$set->artist} - {$set->title}")." out of the Project Loved round \"{$round->name}\"");

        return $this->back($round, 'success', 'Taken out of the round.');
    }

    public function open(string $round)
    {
        $round = TomYumLovedRound::findOrFail((int) $round);
        if (!$round->isPicking()) {
            return $this->back($round, 'error', 'Voting is open already.');
        }
        if (!$round->nominations()->exists()) {
            return $this->back($round, 'error', 'Add some maps first.');
        }
        $days = get_int(Request::input('days')) ?? 7;
        if ($days < 1 || $days > 60) {
            return $this->back($round, 'error', 'Voting runs for 1 to 60 days.');
        }

        $made = TomYumLoved::openVoting($round, $days);
        $this->staffLog('loved.open', $round->getKey(), "Opened voting on the Project Loved round \"{$round->name}\" for {$days} days ({$made} polls)");

        return redirect(route('panel.loved.show', ['round' => $round->getKey()]))->with('panel_status', [
            'type' => 'success',
            'message' => "Voting is open: {$made} polls posted, for {$days} days.",
            'link' => ['url' => route('loved.rounds.show', ['round' => $round->getKey()]), 'text' => 'see the round'],
        ]);
    }

    public function close(string $round)
    {
        $round = TomYumLovedRound::findOrFail((int) $round);
        if (!$round->isVoting()) {
            return $this->back($round, 'error', 'Only a round with open voting can be closed.');
        }

        $summary = TomYumLoved::close($round, Auth::user());
        $loved = count($summary['loved']);
        $closed = count($summary['errors']) === 0;
        $message = ($closed ? 'Closed' : 'Counted')
            .": {$summary['passed']} passed, {$summary['failed']} didn't. Loved {$loved} set".($loved === 1 ? '' : 's').'.';
        foreach ($summary['skipped'] as $setId => $why) {
            $message .= " Set {$setId} wasn't loved: {$why}.";
        }
        foreach ($summary['errors'] as $setId => $why) {
            $message .= " Set {$setId} couldn't be loved ({$why}).";
        }
        if (!$closed) {
            $message .= ' The round stays open: close it again to retry.';
        }
        $this->staffLog('loved.close', $round->getKey(), ($closed ? 'Closed' : 'Tried to close')." the Project Loved round \"{$round->name}\"", $message);

        return $this->back($round, $closed ? 'success' : 'error', $message);
    }

    /** The round's pass rule from the form: percent of yes votes and fewest votes. */
    private function thresholds(): array
    {
        return [
            'threshold' => max(1, min(100, get_int(Request::input('threshold')) ?? 70)),
            'min_votes' => max(1, min(10000, get_int(Request::input('min_votes')) ?? 10)),
        ];
    }

    private function back(?TomYumLovedRound $round, string $type, string $message, ?TomYumLovedNomination $nomination = null)
    {
        $url = $round === null ? route('panel.loved.index') : route('panel.loved.show', ['round' => $round->getKey()]);
        if ($nomination !== null) {
            $url .= '#nomination-'.$nomination->getKey();
        }

        return redirect($url)->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
