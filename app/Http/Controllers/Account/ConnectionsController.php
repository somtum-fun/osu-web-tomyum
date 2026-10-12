<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Libraries\TomYumConnections;
use Auth;
use Request;

/**
 * TomYum: linking Discord and the player's osu! account (TomYumConnections), from the account
 * settings' Connections section; and the links as JSON for BN Management (QAT).
 */
class ConnectionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['me']]);
        $this->middleware('verify-user', ['except' => ['me', 'callback']]);
    }

    public function create(string $kind)
    {
        abort_unless(in_array($kind, TomYumConnections::KINDS, true) && TomYumConnections::configured($kind), 404);

        $state = bin2hex(random_bytes(16));
        session()->put("tomyum_connect_state.{$kind}", $state);

        return redirect(TomYumConnections::authorizeUrl($kind, $state));
    }

    public function callback(string $kind)
    {
        abort_unless(in_array($kind, TomYumConnections::KINDS, true) && TomYumConnections::configured($kind), 404);

        $expected = session()->pull("tomyum_connect_state.{$kind}");
        $label = TomYumConnections::LABELS[$kind];
        if (Request::input('error') !== null) {
            return $this->back('error', "{$label} wasn't linked.");
        }
        if ($expected === null || !hash_equals($expected, (string) Request::input('state'))) {
            return $this->back('error', 'That link has expired. Please try again.');
        }

        $error = TomYumConnections::link(Auth::user(), $kind, (string) Request::input('code'));

        return $error === null
            ? $this->back('success', "{$label} linked.")
            : $this->back('error', $error);
    }

    public function destroy(string $kind)
    {
        abort_unless(in_array($kind, TomYumConnections::KINDS, true), 404);
        TomYumConnections::unlink(Auth::user(), $kind);

        return $this->back('success', TomYumConnections::LABELS[$kind].' unlinked.');
    }

    /** "Show on my profile" for a linked osu!, Discord or Last.fm account. */
    public function profile(string $kind)
    {
        abort_unless(in_array($kind, TomYumConnections::PROFILE_KINDS, true), 404);
        $shown = Request::boolean('shown');
        TomYumConnections::setShownOnProfile(Auth::user(), $kind, $shown);

        $label = $kind === 'lastfm' ? 'Last.fm' : TomYumConnections::LABELS[$kind];
        $message = $shown ? "Your {$label} account shows on your profile." : "Your {$label} account is hidden from your profile.";

        return $kind === 'lastfm'
            ? redirect(route('account.edit').'#lastfm')->with('lastfm_status', ['type' => 'success', 'message' => $message])
            : $this->back('success', $message);
    }

    /** GET /api/v2/me/tomyum-connections: what the signed-in player has linked (QAT's BN application). */
    public function me()
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return TomYumConnections::status($user);
    }

    private function back(string $type, string $message)
    {
        return redirect(route('account.edit').'#connections')->with('connections_status', ['type' => $type, 'message' => $message]);
    }
}
