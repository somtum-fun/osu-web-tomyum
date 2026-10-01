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

        return ext_view('panel.index', [
            'panels' => [
                'bn' => static::canUseBn($user),
                'gmt' => static::canUseGmt($user),
                'admin' => static::canUseAdmin($user),
                'bots' => \App\Libraries\TomYumBots::canManageAll($user),
            ],
        ]);
    }
}
