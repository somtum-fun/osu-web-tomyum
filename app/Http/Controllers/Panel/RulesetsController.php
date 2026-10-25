<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumCustomRulesets;
use App\Models\TomYumCustomRuleset;
use Auth;
use Illuminate\Database\QueryException;
use Request;

/**
 * TomYum: admin panel, custom lazer rulesets (TomYumCustomRulesets). Lists rulesets.info's rulesets; enabling one pins
 * its current release (URL, SHA-256, size), and "Pin again" moves it to a newer release. Disabled ones keep their id
 * and their scores, but no new play submits.
 */
class RulesetsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseAdmin(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        try {
            $ours = TomYumCustomRuleset::orderBy('id')->get()->keyBy('slug');
            $ready = true;
        } catch (QueryException) {
            $ours = collect();
            $ready = false;
        }

        return ext_view('panel.admin.rulesets', [
            'listing' => TomYumCustomRulesets::listing(Request::boolean('refresh')),
            'ours' => $ours,
            'ready' => $ready,
        ]);
    }

    public function pin(string $slug)
    {
        try {
            $ruleset = TomYumCustomRulesets::pin($slug, Auth::user());
        } catch (\RuntimeException $e) {
            return $this->back('error', $e->getMessage());
        }
        $this->staffLog('ruleset.pin', $ruleset->id, "Custom ruleset {$ruleset->name} ({$ruleset->slug}, id {$ruleset->id}) pinned to ".($ruleset->version ?? 'its current file')." (sha256 ".substr($ruleset->sha256, 0, 12).'…).', $ruleset->version);

        return $this->back('success', "{$ruleset->name} is on, pinned to ".($ruleset->version ?? 'its current file').'. TumThai players can install it in Settings.');
    }

    public function disable(string $slug)
    {
        $ruleset = TomYumCustomRulesets::disable($slug);
        if ($ruleset === null) {
            return $this->back('error', 'That ruleset isn\'t on.');
        }
        $this->staffLog('ruleset.disable', $ruleset->id, "Custom ruleset {$ruleset->name} ({$ruleset->slug}) turned off.");

        return $this->back('success', "{$ruleset->name} is off: no new plays submit. Its scores stay.");
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.rulesets.index'))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
