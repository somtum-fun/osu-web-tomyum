<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Libraries\TomYumCustomRulesetScores;
use App\Libraries\TomYumCustomRulesets;

/**
 * TomYum: the custom lazer rulesets staff turned on, with their pinned files (GET /api/v2/tomyum/rulesets). TumThai
 * reads it (signed in) for its Settings list, its downloads and the id a ruleset's plays submit with.
 */
class TomYumRulesetsController extends Controller
{
    /** /rankings/rulesets/{slug}: a custom ruleset's players by ranked score, worldwide or in one country. */
    public function rankings(?string $slug = null)
    {
        $rulesets = array_values(TomYumCustomRulesets::enabled());
        if ($rulesets === []) {
            abort(404, 'There are no custom rulesets here yet.');
        }
        if ($slug === null) {
            return redirect(route('rankings.custom-rulesets', ['slug' => $rulesets[0]->slug]));
        }
        $ruleset = TomYumCustomRulesets::findBySlug($slug) ?? abort(404, 'No such ruleset.');

        $country = presence(get_string(request('country')));
        $country = $country !== null && preg_match('/^[A-Za-z]{2}$/', $country) === 1 ? strtoupper($country) : null;
        $page = max(1, min(200, get_int(request('page')) ?? 1));
        $rankings = TomYumCustomRulesetScores::rankings($ruleset, $country, $page);
        $users = \App\Models\User::with('country')->whereIn('user_id', array_column($rankings['rows'], 'user_id'))->get()->keyBy('user_id');

        $scores = new \Illuminate\Pagination\LengthAwarePaginator(
            $rankings['rows'],
            $rankings['total'],
            TomYumCustomRulesetScores::RANKINGS_PAGE,
            $page,
            ['path' => route('rankings.custom-rulesets', ['slug' => $ruleset->slug]), 'query' => array_filter(['country' => $country])],
        );

        return ext_view('rankings.custom_rulesets', [
            'country' => $country,
            'ruleset' => $ruleset,
            'rulesets' => $rulesets,
            'scores' => $scores,
            'users' => $users,
        ]);
    }

    public function index()
    {
        return response()->json([
            'rulesets' => array_values(array_map(
                fn ($ruleset) => TomYumCustomRulesets::toApi($ruleset),
                TomYumCustomRulesets::enabled(),
            )),
        ]);
    }
}
