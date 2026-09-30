<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use Auth;
use DB;
use Request;
use Schema;

/**
 * Admin panel: lazer mods that count as ranked here (pp, medals) besides osu!'s own. The choices are
 * in tomyum_ranked_mods, which TomYum's build of the score processor makes, fills with what osu! ranks
 * and reads; when one changes it works out the pp of old scores with that mod again. See TomYum's
 * deploy/score-processor and docs/lazer.md.
 */
class ModsController extends Controller
{
    public const RULESETS = ['osu' => [0, 'osu!'], 'taiko' => [1, 'osu!taiko'], 'fruits' => [2, 'osu!catch'], 'mania' => [3, 'osu!mania']];

    /** Not offered: Relax/Autopilot have their own leaderboards here, Classic marks stable plays. */
    private const LEFT_OUT = ['RX', 'AP', 'CL'];

    private const TABLE = 'tomyum_ranked_mods';

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
        [$rulesetName, $rulesetId] = $this->ruleset();
        $ready = Schema::hasTable(static::TABLE);
        $rows = $ready
            ? DB::table(static::TABLE)->where('ruleset_id', $rulesetId)->get()->keyBy('acronym')
            : collect();

        $types = ['DifficultyReduction' => 'Difficulty reduction', 'DifficultyIncrease' => 'Difficulty increase',
            'Conversion' => 'Conversion', 'Automation' => 'Automation', 'Fun' => 'Fun', 'System' => 'System'];
        $groups = [];
        foreach ($this->lazerMods($rulesetId) as $mod) {
            $row = $rows[$mod['Acronym']] ?? null;
            $groups[$types[$mod['Type']] ?? $mod['Type']][] = [
                'acronym' => $mod['Acronym'],
                'name' => $mod['Name'],
                'description' => $mod['Description'],
                'has_settings' => count($mod['Settings'] ?? []) > 0,
                'osu_ranked' => $row === null ? null : (bool) $row->osu_ranked,
                'ranked' => $row !== null && (bool) $row->ranked,
                'pending' => $row !== null && (bool) $row->ranked !== (bool) $row->applied,
            ];
        }

        return ext_view('panel.admin.mods', [
            'currentRuleset' => $rulesetName,
            'groups' => $groups,
            'ready' => $ready && $rows->isNotEmpty(),
        ]);
    }

    public function update()
    {
        [$rulesetName, $rulesetId] = $this->ruleset();
        if (!Schema::hasTable(static::TABLE)) {
            return $this->back('error', "The score processor hasn't set up the mod list yet.");
        }

        $offered = array_column($this->lazerMods($rulesetId), 'Acronym');
        $wanted = array_values(array_intersect($offered, (array) Request::input('ranked', [])));
        $before = DB::table(static::TABLE)->where('ruleset_id', $rulesetId)->where('ranked', true)->pluck('acronym')->all();

        $added = array_values(array_diff($wanted, $before));
        $removed = array_values(array_intersect(array_diff($before, $wanted), $offered));
        if (count($added) === 0 && count($removed) === 0) {
            return $this->back('success', 'Nothing changed.');
        }

        DB::transaction(function () use ($rulesetId, $added, $removed) {
            foreach ($added as $acronym) {
                DB::statement(
                    'INSERT INTO '.static::TABLE.' (ruleset_id, acronym, ranked) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE ranked = 1',
                    [$rulesetId, $acronym],
                );
            }
            if (count($removed) > 0) {
                DB::table(static::TABLE)->where('ruleset_id', $rulesetId)->whereIn('acronym', $removed)->update(['ranked' => false]);
            }
        });

        $label = static::RULESETS[$rulesetName][1];
        $parts = [];
        if (count($added) > 0) {
            $parts[] = 'ranked '.implode(', ', $added);
        }
        if (count($removed) > 0) {
            $parts[] = 'unranked '.implode(', ', $removed);
        }
        $this->staffLog('mods.ranked', $rulesetId, ucfirst(implode('; ', $parts))." in {$label}");

        return $this->back('success', ucfirst(implode('; ', $parts))." in {$label}. Old scores with them get their pp worked out again within a minute or so.");
    }

    /** @return array{0: string, 1: int} */
    private function ruleset(): array
    {
        $name = (string) Request::input('mode', 'osu');
        $name = isset(static::RULESETS[$name]) ? $name : 'osu';

        return [$name, static::RULESETS[$name][0]];
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.mods.index', ['mode' => $this->ruleset()[0]]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }

    /** The mods a lazer player can pick in a ruleset (osu-web's copy of lazer's list), minus the ones not offered. */
    private function lazerMods(int $rulesetId): array
    {
        static $all = null;
        $all ??= json_decode(file_get_contents(database_path('mods.json')), true);

        foreach ($all as $ruleset) {
            if ($ruleset['RulesetID'] === $rulesetId) {
                return array_values(array_filter(
                    $ruleset['Mods'],
                    fn ($mod) => ($mod['UserPlayable'] ?? false) && !in_array($mod['Acronym'], static::LEFT_OUT, true),
                ));
            }
        }

        return [];
    }
}
