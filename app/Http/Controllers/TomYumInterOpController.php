<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Models\Beatmapset;

/**
 * InterOp endpoints for the TomYum game server (under /_lio, same signed auth as LegacyInterOp).
 */
class TomYumInterOpController extends Controller
{
    public function indexBeatmapsetDocument($id)
    {
        Beatmapset::withTrashed()->findOrFail($id)->esIndexDocument();

        return response()->noContent();
    }
}
