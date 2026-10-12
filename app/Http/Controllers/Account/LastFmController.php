<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Account;

use App\Exceptions\InvariantException;
use App\Http\Controllers\Controller;
use App\Libraries\TomYumAdmin;
use App\Models\User;
use Auth;

/**
 * TomYum: connecting a Last.fm account (account settings), so the game server scrobbles the player's
 * plays. The game server keeps Last.fm's key and the player's session; this sends the player to
 * Last.fm to approve and hands the token Last.fm sends back to the game server.
 */
class LastFmController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('verify-user');

        parent::__construct();
    }

    /**
     * The player's Last.fm state: configured (the server has a Last.fm key), username (connected
     * account or null), authUrl. Null when the game server can't be reached.
     */
    public static function status(User $user): ?array
    {
        try {
            $response = (new TomYumAdmin())->get("/lastfm/{$user->getKey()}", ['callback' => route('account.lastfm.callback')]);
        } catch (InvariantException $e) {
            return null;
        }

        return $response->successful() ? $response->json() : null;
    }

    /** The history list again (the account page refreshes it every 10 seconds). */
    public function recent()
    {
        $recent = static::status(Auth::user())['recent'] ?? [];

        return response(view('accounts._lastfm_recent', ['recent' => $recent])->render())
            ->header('Cache-Control', 'no-store');
    }

    /** Off to Last.fm to approve the server. */
    public function create()
    {
        $url = static::status(Auth::user())['authUrl'] ?? null;
        abort_if($url === null, 404);

        return redirect()->away($url);
    }

    /** Back from Last.fm with a token (or without one when the player said no). */
    public function callback()
    {
        $token = presence(get_string(request('token')));
        if ($token === null) {
            return $this->back('error', "Last.fm wasn't connected.");
        }

        try {
            $response = (new TomYumAdmin(Auth::user()))->post('/lastfm/'.Auth::id().'/link', ['token' => $token]);
        } catch (InvariantException $e) {
            return $this->back('error', "The game server couldn't be reached; try again in a moment.");
        }

        return $response->successful()
            ? $this->back('success', 'Connected as '.$response->json('username').'. Your plays will show up on Last.fm.')
            : $this->back('error', $response->json('error') ?? "Last.fm wasn't connected.");
    }

    public function destroy()
    {
        try {
            (new TomYumAdmin(Auth::user()))->post('/lastfm/'.Auth::id().'/unlink');
        } catch (InvariantException $e) {
            return $this->back('error', "The game server couldn't be reached; try again in a moment.");
        }
        \App\Libraries\TomYumConnections::setShownOnProfile(Auth::user(), 'lastfm', false);

        return $this->back('success', 'Disconnected from Last.fm.');
    }

    private function back(string $type, string $message)
    {
        return redirect(route('account.edit').'#lastfm')->with('lastfm_status', compact('type', 'message'));
    }
}
