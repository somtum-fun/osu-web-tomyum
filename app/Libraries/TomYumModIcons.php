<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: mods drawn as the website's mod icons (resources/css/bem/mod.less) in the staff panels
 * (panel/_mods.blade.php). An icon's colour comes from its type, read from osu-web's copy of lazer's
 * mod list (database/mods.json).
 */
class TomYumModIcons
{
    /** The stable mod bits, in osu!'s order, for a play's mods as a number. */
    private const STABLE_BITS = [
        1 => 'NF', 2 => 'EZ', 4 => 'TD', 8 => 'HD', 16 => 'HR', 32 => 'SD', 64 => 'DT', 128 => 'RX',
        256 => 'HT', 512 => 'NC', 1024 => 'FL', 2048 => 'AT', 4096 => 'SO', 8192 => 'AP', 16384 => 'PF',
        32768 => '4K', 65536 => '5K', 131072 => '6K', 262144 => '7K', 524288 => '8K', 1048576 => 'FI',
        2097152 => 'RD', 4194304 => 'CN', 8388608 => 'TP', 16777216 => '9K', 33554432 => 'CO',
        67108864 => '1K', 134217728 => '3K', 268435456 => '2K', 536870912 => 'V2', 1073741824 => 'MR',
    ];

    /** A mod's type (DifficultyIncrease, Automation…), for its colour; System when it isn't known. */
    public static function type(string $acronym, int $rulesetId = 0): string
    {
        static $types = null;
        if ($types === null) {
            $types = [];
            foreach (json_decode(file_get_contents(database_path('mods.json')), true) as $ruleset) {
                foreach ($ruleset['Mods'] as $mod) {
                    $types[$ruleset['RulesetID']][$mod['Acronym']] = $mod['Type'];
                }
            }
        }

        return $types[$rulesetId][$acronym] ?? $types[0][$acronym] ?? 'System';
    }

    /**
     * Acronyms from what the panels hold: a list of acronyms, lazer's mod JSON (a string, or arrays
     * or objects with "acronym"), or a stable mod number.
     *
     * @return string[]
     */
    public static function acronyms($mods): array
    {
        if ($mods === null || $mods === '' || $mods === []) {
            return [];
        }
        if (is_int($mods) || (is_string($mods) && ctype_digit($mods))) {
            $bits = (int) $mods;
            // Nightcore and Perfect come with Double Time and Sudden Death set too: show only them.
            if (($bits & 512) !== 0) {
                $bits &= ~64;
            }
            if (($bits & 16384) !== 0) {
                $bits &= ~32;
            }

            return array_values(array_filter(static::STABLE_BITS, fn ($bit) => ($bits & $bit) !== 0, ARRAY_FILTER_USE_KEY));
        }
        if (is_string($mods)) {
            $mods = json_decode($mods, true) ?? [];
        }

        return array_values(array_filter(array_map(
            fn ($mod) => is_string($mod) ? $mod : (is_array($mod) ? $mod['acronym'] ?? null : $mod->acronym ?? null),
            array_values((array) $mods),
        )));
    }
}
