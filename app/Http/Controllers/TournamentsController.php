<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Models\Beatmap;
use App\Models\Tournament;
use App\Models\UserStatistics;
use Auth;

class TournamentsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['only' => [
            'register',
            'unregister', // TomYum
        ]]);

        parent::__construct();
    }

    public function index()
    {
        return ext_view('tournaments.index', [
            'listing' => Tournament::getGroupedListing(),
        ]);
    }

    public function show($id)
    {
        $tournament = $this->findVisible($id);
        $user = Auth::user();

        // TomYum: the registered players (with their rank in the tournament's mode), and for the
        // player looking, which requirements they don't meet.
        $registrations = $tournament->registrations()->with('user')->orderBy('registration_id')->get()
            ->filter(fn ($registration) => $registration->user !== null && !$registration->user->isRestricted());
        $ranks = [];
        $statsClass = UserStatistics\Model::getClass($tournament->playModeStr(), $tournament->play_mode_variant);
        foreach ($registrations->pluck('user_id')->chunk(1000) as $ids) {
            foreach ($statsClass::whereIn('user_id', $ids)->get() as $stats) {
                $ranks[$stats->user_id] = $stats->globalRank();
            }
        }

        return ext_view('tournaments.show', [
            'tournament' => $tournament,
            'registrations' => $registrations,
            'ranks' => $ranks,
            'requirements' => $tournament->requirements(),
            'failures' => $user === null ? [] : $tournament->registrationFailures($user),
            'isSignedUp' => $tournament->isSignedUp($user),
            'mode' => Beatmap::modeStr($tournament->play_mode),
        ]);
    }

    public function unregister($id)
    {
        $tournament = $this->findVisible($id);

        if (!$tournament->isRegistrationOpen()) {
            return error_popup(osu_trans('tournament.requirements.failed.closed')); // TomYum: friendly
        }

        $tournament->unregister(Auth::user());

        return ujs_redirect(route('tournaments.show', $tournament));
    }

    public function register($id)
    {
        $tournament = $this->findVisible($id);
        $user = Auth::user();

        // TomYum: every requirement they don't meet, in plain words (the page lists them too).
        $failures = $tournament->registerChecked($user);
        if (count($failures) > 0) {
            return error_popup(implode(' ', $failures));
        }

        return ujs_redirect(route('tournaments.show', $tournament));
    }

    // TomYum: drafts and the banners page's rows are only for staff.
    private function findVisible($id): Tournament
    {
        $tournament = Tournament::findOrFail($id);
        if (!$tournament->isVisibleTo(Auth::user())) {
            abort(404);
        }

        return $tournament;
    }
}
