<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Libraries\TomYumLazerVersions;
use Closure;
use Illuminate\Http\Request;

/**
 * TomYum: refuses an API request from a TumThai (lazer) build staff haven't allowed. See
 * {@see TomYumLazerVersions} for how the setting works; off until staff set a minimum or whitelist.
 */
class TomYumLazerVersionGate
{
    public function handle(Request $request, Closure $next)
    {
        $apiVersion = get_int($request->header('x-api-version')) ?? 0;
        if (!TomYumLazerVersions::unrestricted() && !TomYumLazerVersions::allows($apiVersion)) {
            return response()->json(['error' => TomYumLazerVersions::MESSAGE], 403);
        }

        return $next($request);
    }
}
