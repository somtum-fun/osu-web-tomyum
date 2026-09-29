<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * TomYum: this server sells nothing (no products, no payment provider), so every store page, the cart
 * and checkout answer that the store isn't available instead of breaking halfway through an order.
 */
class TomYumStoreUnavailable
{
    public const MESSAGE = 'The osu!store isn\'t available on this server.';

    public function handle(Request $request, Closure $next)
    {
        if (is_json_request() || !$request->isMethod('GET')) {
            return response()->json(['error' => static::MESSAGE], 404);
        }

        return ext_view('store.unavailable', [], null, 404);
    }
}
