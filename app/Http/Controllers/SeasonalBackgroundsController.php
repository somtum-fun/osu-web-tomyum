<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Libraries\TomYumSeasonal;
use App\Models\Contest;
use App\Transformers\SeasonalBackgroundTransformer;
use stdClass;

class SeasonalBackgroundsController extends Controller
{
    public function index()
    {
        // TomYum: the contest and end date the admin panel sets (TomYumSeasonal), unless the env gives them.
        $contest = Contest::find(TomYumSeasonal::contestId());
        $endsAt = TomYumSeasonal::endsAt();

        if ($contest === null || $endsAt === null) {
            return response()->json(new stdClass());
        }

        $backgrounds = $contest->userContestEntries()->where('show_in_client', true)->get();

        return [
            'ends_at' => json_time($endsAt),
            'backgrounds' => json_collection($backgrounds, new SeasonalBackgroundTransformer()),
        ];
    }
}
