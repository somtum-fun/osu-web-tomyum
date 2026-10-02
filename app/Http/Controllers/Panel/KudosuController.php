<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Models\KudosuHistory;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use DB;
use Request;

/**
 * Kudosu by hand (TomYum): admins and developers add or take a player's kudosu with a reason. The
 * entry shows on the player's profile ("Received 5 kudosu from peppy: …") like modding kudosu does.
 */
class KudosuController extends Controller
{
    /** The most one change may add or take. */
    private const MAX_CHANGE = 10000;

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canAdjustKudosu(Auth::user()));

            return $next($request);
        });
    }

    public static function canAdjustKudosu(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isDev());
    }

    public function index()
    {
        $query = trim(Request::input('user') ?? '');
        $target = null;
        if ($query !== '') {
            $target = ctype_digit($query) ? User::find((int) $query) : User::lookup($query, 'username');
        }

        return ext_view('panel.kudosu.index', [
            'query' => $query,
            'target' => $target,
            'history' => $target === null ? collect() : KudosuHistory::where('receiver_id', $target->getKey())
                ->with('giver')
                ->orderByDesc('exchange_id')
                ->limit(25)
                ->get(),
        ]);
    }

    public function adjust(string $user)
    {
        $target = User::findOrFail((int) $user);
        $amount = (int) Request::input('amount');
        if (Request::input('direction') === 'take') {
            $amount = -abs($amount);
        } else {
            $amount = abs($amount);
        }
        $reason = trim(Request::input('reason') ?? '');

        if ($amount === 0 || abs($amount) > static::MAX_CHANGE) {
            return $this->back($target, 'error', 'Pick an amount from 1 to '.number_format(static::MAX_CHANGE).'.');
        }
        if ($reason === '') {
            return $this->back($target, 'error', 'Give a reason: it shows on their profile.');
        }
        // Never below zero.
        if ($amount < 0) {
            $amount = -min(-$amount, max(0, $target->osu_kudostotal));
            if ($amount === 0) {
                return $this->back($target, 'error', 'They have no kudosu to take.');
            }
        }

        $actor = Auth::user();
        DB::transaction(function () use ($target, $actor, $amount, $reason) {
            KudosuHistory::create([
                'receiver_id' => $target->getKey(),
                'giver_id' => $actor->getKey(),
                'amount' => $amount,
                'action' => $amount > 0 ? 'give' : 'reset',
                'date' => Carbon::now(),
                'details' => ['tomyum' => 'staff', 'reason' => mb_substr($reason, 0, 255)],
            ]);
            $target->update([
                'osu_kudostotal' => DB::raw("GREATEST(0, osu_kudostotal + {$amount})"),
                'osu_kudosavailable' => DB::raw("GREATEST(0, osu_kudosavailable + {$amount})"),
            ]);
        });

        $verb = $amount > 0 ? 'Gave' : 'Took';
        $summary = "{$verb} ".abs($amount)." kudosu ".($amount > 0 ? 'to' : 'from')." {$target->username}: {$reason}";
        $this->staffLog('user.kudosu', $target->getKey(), $summary);

        return $this->back($target, 'success', $summary.'.');
    }

    private function back(User $target, string $type, string $message)
    {
        return redirect(route('panel.kudosu.index', ['user' => $target->getKey()]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
