<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Libraries\TomYumMaintenance;
use Closure;
use Illuminate\Http\Request;

/**
 * TomYum: during maintenance, the API answers players who aren't staff "unavailable" (503), so lazer
 * shows it's reconnecting and keeps trying until maintenance ends. Score submission stays open (a play
 * in progress is still submitted), and so do requests without a player (client credentials).
 */
class TomYumMaintenanceGate
{
    private const OPEN = [
        'api/v2/beatmaps/*/solo/scores',
        'api/v2/beatmaps/*/solo/scores/*',
        'api/v2/rooms/*/playlist/*/scores',
        'api/v2/rooms/*/playlist/*/scores/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if ($user !== null && !$request->is(...static::OPEN) && TomYumMaintenance::isLockedFor($user)) {
            return response()->json(['error' => TomYumMaintenance::notice()], 503, ['Retry-After' => '30']);
        }

        return $next($request);
    }
}
