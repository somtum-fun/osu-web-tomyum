<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Models\UserClient;

class ClientVerificationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['only' => 'store']);
        $this->middleware('verify-user');
        $this->middleware('throttle:60,10');

        parent::__construct();
    }

    public function create()
    {
        if (!auth()->check()) {
            return ext_view('sessions.create', null, null, 401);
        }

        $hash = static::hash();
        // TomYum: osu!stable names the account it's logging in (u); confirming the device while
        // signed in as another would verify it for the wrong one.
        $expected = presence(get_string(request('u')));
        $wrongAccount = $expected !== null && !static::isAccount(auth()->user(), $expected) ? $expected : null;

        // TomYum: no client hash and no device waiting for this account: nothing to confirm yet.
        if ($hash === null) {
            $nothingPending = true;

            return ext_view('client_verifications.create', compact('expected', 'hash', 'nothingPending', 'wrongAccount'));
        }

        $client = UserClient::lookupOrNew(auth()->user()->getKey(), $hash);

        if ($client->verified && $wrongAccount === null) {
            return ext_view('client_verifications.completed');
        }

        return ext_view('client_verifications.create', compact('expected', 'hash', 'wrongAccount'));
    }

    public function store()
    {
        $hash = static::hash();
        if ($hash === null) {
            return error_popup('There\'s no device waiting to be confirmed. Log in from the game first, then try again.');
        }
        $expected = presence(get_string(request('u')));
        if ($expected !== null && !static::isAccount(auth()->user(), $expected)) {
            return error_popup("This device is waiting for {$expected}: sign in as {$expected} to confirm it.");
        }
        UserClient::markVerified(auth()->user()->getKey(), $hash);

        return ujs_redirect(route('client-verifications.create', ['ch' => $hash, 'u' => $expected]));
    }

    /**
     * TomYum: the client hash to confirm: the one the game sent (ch), else the signed-in account's newest
     * device still waiting (old builds and the hook don't send it), or null when there's none.
     */
    private static function hash(): ?string
    {
        return presence(get_string(request('ch'))) ?? UserClient::pendingHash(auth()->user()->getKey());
    }

    /** TomYum: whether $name is $user (osu! names ignore case, and a space and an underscore match). */
    private static function isAccount($user, string $name): bool
    {
        $normalize = fn (string $value) => strtr(mb_strtolower(trim($value)), '_', ' ');

        return $normalize($user->username) === $normalize($name);
    }
}
