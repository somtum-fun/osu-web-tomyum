<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use Auth;

class PagesController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $this->ensure(static::canUseAny($user));

        $ticketCategories = \App\Libraries\TomYumTickets::categoriesFor($user);

        return ext_view('panel.index', [
            'openTickets' => count($ticketCategories) === 0 ? 0
                : \DB::table('tomyum_tickets')->whereIn('category', $ticketCategories)->where('status', 'open')->count(),
            'panels' => [
                'bn' => static::canUseBn($user),
                'gmt' => static::canUseGmt($user),
                'tc' => static::canUseTc($user),
                'loved' => static::canUseLoved($user),
                'admin' => static::canUseAdmin($user),
                'bots' => \App\Libraries\TomYumBots::canManageAll($user),
                'kudosu' => KudosuController::canAdjustKudosu($user),
                'tickets' => TicketsController::canUseTickets($user),
            ],
        ]);
    }
}
