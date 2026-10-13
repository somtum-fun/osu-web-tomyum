<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Libraries\TomYumAdmin;
use App\Libraries\TomYumModeScores;
use App\Models\Solo\Score;

/**
 * TomYum: the page of a Relax/Autopilot play (TomYum keeps those; the website has none), shown with
 * the website's own score page, and its replay.
 */
class TomYumScoresController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware('auth', ['only' => ['download']]);
    }

    public function show($id)
    {
        [$scoreJson, $user, $beatmap] = TomYumModeScores::one((int) $id) ?? abort(404);

        if (is_json_request()) {
            return $scoreJson;
        }

        // The page's title and raw data, from a score that's only shown (never saved).
        $score = new Score([
            'beatmap_id' => $beatmap->getKey(),
            'user_id' => $user->getKey(),
            'ruleset_id' => $scoreJson['ruleset_id'],
        ]);
        $score->setRelation('beatmap', $beatmap);
        $score->setRelation('user', $user);

        return ext_view('scores.show', compact('score', 'scoreJson'));
    }

    public function download($id)
    {
        // Only a score the viewer may see (not a restricted player's: TomYumModeScores::one).
        TomYumModeScores::one((int) $id) ?? abort(404);
        $response = (new TomYumAdmin(auth()->user()))->get('/replays/mode-score/'.((int) $id));
        abort_unless($response->successful(), 404);

        return response($response->body(), 200, [
            'Content-Type' => 'application/x-osu-replay',
            'Content-Disposition' => $response->header('Content-Disposition') ?: 'attachment; filename="replay.osr"',
        ]);
    }
}
