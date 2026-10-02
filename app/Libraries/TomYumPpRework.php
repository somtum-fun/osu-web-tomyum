<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\User;
use Cache;

/**
 * TomYum: pp reworks as the game server runs them (`/_admin/pp-reworks`; started from the admin
 * panel, or after a pp library update). While one runs, its modes' rankings stay as they were (the
 * vanilla ones from a snapshot, `tomyum_ranking_snapshots`) and new scores' pp waits. Read at most
 * every few seconds; if the game server can't be reached, none is running.
 */
class TomYumPpRework
{
    private const CACHE_KEY = 'tomyum:pp-rework-status';

    private const CACHE_SECONDS = 5;

    /** TomYum's modes => [ruleset, variant, name]. */
    const MODES = [
        0 => ['osu', null, 'osu!'],
        1 => ['taiko', null, 'osu!taiko'],
        2 => ['fruits', null, 'osu!catch'],
        3 => ['mania', null, 'osu!mania'],
        4 => ['osu', 'rx', 'osu! Relax'],
        5 => ['taiko', 'rx', 'osu!taiko Relax'],
        6 => ['fruits', 'rx', 'osu!catch Relax'],
        8 => ['osu', 'ap', 'osu! Autopilot'],
    ];

    /** ['active' => [rework...], 'recent' => [rework...]], each with modes, state, total, done, websiteTotal, websiteLeft, ... */
    public static function status(): array
    {
        return Cache::remember(static::CACHE_KEY, static::CACHE_SECONDS, function () {
            try {
                $response = (new TomYumAdmin(static::actor()))->get('/pp-reworks');

                return $response->successful() ? $response->json() : static::none();
            } catch (\Throwable $e) {
                return static::none();
            }
        });
    }

    public static function forget(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /** The running reworks. */
    public static function active(): array
    {
        return static::status()['active'] ?? [];
    }

    /** The modes being reworked (TomYum's ids). */
    public static function modes(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn ($rework) => $rework['modes'] ?? [], static::active()))));
    }

    /** The rework running in a ruleset (and variant: null vanilla, 'rx', 'ap'), if any. */
    public static function forRuleset(string $ruleset, ?string $variant = null): ?array
    {
        foreach (static::active() as $rework) {
            foreach ($rework['modes'] ?? [] as $mode) {
                [$modeRuleset, $modeVariant] = static::MODES[$mode] ?? [null, null];
                if ($modeRuleset === $ruleset && $modeVariant === $variant) {
                    return $rework;
                }
            }
        }

        return null;
    }

    public static function modeNames(array $modes): string
    {
        return implode(', ', array_map(fn ($mode) => static::MODES[$mode][2] ?? "mode {$mode}", $modes));
    }

    /** How far a rework has got, in words. */
    public static function progress(array $rework): string
    {
        if (($rework['state'] ?? null) === 'website') {
            $total = max(1, $rework['websiteTotal'] ?? 0);
            $left = $rework['websiteLeft'] ?? 0;

            return 'Updating the website: '.i18n_number_format(max(0, $total - $left)).' of '.i18n_number_format($total).' scores';
        }

        return 'Recalculating: '.i18n_number_format($rework['done'] ?? 0).' of '.i18n_number_format($rework['total'] ?? 0).' scores';
    }

    /** 0-100 overall. */
    public static function percent(array $rework): float
    {
        if (($rework['state'] ?? null) === 'website') {
            $total = max(1, $rework['websiteTotal'] ?? 0);

            return 90 + 10 * (max(0, $total - ($rework['websiteLeft'] ?? 0)) / $total);
        }
        $total = max(1, $rework['total'] ?? 0);
        $hasWebsite = count(array_filter($rework['modes'] ?? [], fn ($mode) => $mode < 4)) > 0;

        return min(100, ($hasWebsite ? 90 : 100) * (($rework['done'] ?? 0) / $total));
    }

    private static function actor(): ?User
    {
        return User::find($GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id']);
    }

    private static function none(): array
    {
        return ['active' => [], 'recent' => []];
    }
}
