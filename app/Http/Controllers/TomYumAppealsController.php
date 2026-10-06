<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Libraries\TomYumAppeals;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Request;

/**
 * TomYum: the appeal form (/home/appeal). A restricted player sends it once their appeal cooldown is
 * over; it becomes an appeal ticket the GMT answer in the staff panel, and replies go to the
 * account's email address. One appeal waits for an answer at a time.
 */
class TomYumAppealsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        parent::__construct();
    }

    public function show()
    {
        return ext_view('tomyum-appeals.show', static::state(Auth::user()) + [
            'sentTicket' => session('tomyum_appeal_sent'),
        ]);
    }

    public function store()
    {
        $user = Auth::user();
        $state = static::state($user);
        if (!$state['canAppeal']) {
            return redirect(route('tomyum-appeal.show'));
        }

        $answers = [];
        $errors = [];
        foreach (array_keys(TomYumAppeals::QUESTIONS) as $key) {
            $answer = trim(get_string(Request::input($key)) ?? '');
            if ($answer === '') {
                $errors[] = $key;
            }
            $answers[$key] = mb_substr($answer, 0, 3000);
        }
        if (count($errors) > 0) {
            return redirect(route('tomyum-appeal.show'))
                ->withInput()
                ->with('tomyum_appeal_error', 'Please answer every question.');
        }

        $ticketId = TomYumAppeals::submit($user, $answers);

        return redirect(route('tomyum-appeal.show'))->with('tomyum_appeal_sent', $ticketId);
    }

    /** What the page shows: whether they may appeal now, and if not, why. */
    private static function state(User $user): array
    {
        $appealAfter = TomYumAppeals::appealAfter($user);
        $openAppeal = $appealAfter === null ? null : TomYumAppeals::openAppeal($user);
        $hasEmail = present($user->user_email);

        return [
            'appealAfter' => $appealAfter,
            'canAppeal' => $appealAfter !== null && !$appealAfter->isAfter(Carbon::now()) && $openAppeal === null && $hasEmail,
            'hasEmail' => $hasEmail,
            'openAppeal' => $openAppeal,
            'restricted' => $user->isRestricted(),
        ];
    }
}
