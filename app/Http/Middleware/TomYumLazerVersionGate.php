<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Libraries\TomYumLazerVersions;
use Closure;
use Illuminate\Http\Request;

/**
 * TomYum: refuses a *score submission* from a TumThai (lazer) build staff haven't allowed. See
 * {@see TomYumLazerVersions} for how the setting works; off until staff set a minimum or whitelist.
 *
 * The gate used to block the whole API group, which also broke chat, notifications and the
 * profile fetch — a disallowed build couldn't even sign in. Now the login path is open; only
 * the score-token (solo + multiplayer) routes refuse, so the player sees the "upgrade" toast
 * at the moment they finish a play.
 */
class TomYumLazerVersionGate
{
    public function handle(Request $request, Closure $next)
    {
        if (TomYumLazerVersions::unrestricted() || !static::isGated($request)) {
            return $next($request);
        }

        $apiVersion = get_int($request->header('x-api-version')) ?? 0;
        if (!TomYumLazerVersions::allows($apiVersion)) {
            return response()->json(['error' => TomYumLazerVersions::MESSAGE], 403);
        }

        return $next($request);
    }

    /**
     * Only score submission is gated: a disallowed build can sign in, chat and browse, and the
     * "upgrade" toast fires at the moment it finishes a play (<c>SCORE WILL NOT BE SUBMITTED</c>).
     * Login itself (<c>/oauth/token</c>, <c>/api/v2/me</c>) and the realtime endpoints stay open.
     */
    private static function isGated(Request $request): bool
    {
        $method = $request->getMethod();
        if ($method !== 'POST' && $method !== 'PUT') {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');
        // Solo scores: POST .../solo/scores, PUT .../solo/scores/{token}.
        if (preg_match('@/beatmaps/[^/]+/solo/scores(?:/[^/]+)?$@', $path) === 1) {
            return true;
        }
        // Multiplayer scores: /rooms/{id}/playlist/{id}/scores[/{id}].
        if (preg_match('@/rooms/[^/]+/playlist/[^/]+/scores(?:/[^/]+)?$@', $path) === 1) {
            return true;
        }

        return false;
    }
}
