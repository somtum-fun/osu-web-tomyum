<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\TomYumCustomRuleset;
use App\Models\User;
use Cache;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;

/**
 * TomYum: custom lazer rulesets from rulesets.info. Staff enable one in the admin panel, which pins its current release
 * (the DLL's URL, SHA-256 and size); TumThai downloads exactly that file, and a play submits only when the ruleset it
 * ran on has that hash. Their scores are the website's own (ruleset_id 10 and up), ranked by score: no pp.
 */
class TomYumCustomRulesets
{
    /** The rulesets.info API's listing. */
    const LISTING_URL = 'https://rulesets.info/api/rulesets';

    /** Ids GooGuTeam's custom-rulesets builds use, kept so their players' ids match; others get 15 and up. */
    const FIXED_IDS = ['sentakki' => 10, 'tau' => 11, 'rush' => 12, 'hishigata' => 13, 'soyokaze' => 14];

    const FIRST_FREE_ID = 15;

    /** A pinned DLL bigger than this is refused (rulesets are a few MB). */
    const MAX_BYTES = 50 * 1024 * 1024;

    const CACHE_KEY = 'tomyum:custom-rulesets';

    /** Whether the id is a custom ruleset's (ruleset ids 0-3 are osu!'s own; RX/AP aren't rulesets on the website). */
    public static function isCustomId(mixed $rulesetId): bool
    {
        $id = get_int($rulesetId);

        return $id !== null && $id >= 10;
    }

    /**
     * The enabled rulesets by id (cached for the request).
     *
     * @return array<int, TomYumCustomRuleset>
     */
    public static function enabled(): array
    {
        // Shared and short-lived: the site's workers live across requests, and pin()/disable() clear it.
        return Cache::remember(static::CACHE_KEY, 60, function () {
            try {
                return TomYumCustomRuleset::where('enabled', true)->orderBy('id')->get()->keyBy('id')->all();
            } catch (QueryException) {
                return [];
            }
        });
    }

    public static function find(mixed $rulesetId): ?TomYumCustomRuleset
    {
        $id = get_int($rulesetId);

        return $id === null ? null : (static::enabled()[$id] ?? null);
    }

    public static function findBySlug(mixed $slug): ?TomYumCustomRuleset
    {
        if (!is_string($slug)) {
            return null;
        }
        foreach (static::enabled() as $ruleset) {
            if (strcasecmp($ruleset->slug, $slug) === 0) {
                return $ruleset;
            }
        }

        return null;
    }

    /**
     * Refuses a score token for a custom ruleset that isn't turned on, or whose DLL isn't the pinned one (by the SHA-256
     * TumThai sends as ruleset_hash): the game shows why under "Score will not be submitted".
     */
    public static function refuseUnpinned(mixed $rulesetId, mixed $hash): void
    {
        if (!static::isCustomId($rulesetId)) {
            return;
        }
        $ruleset = static::find($rulesetId);
        if ($ruleset === null) {
            abort(422, 'This ruleset isn\'t ranked on this server.');
        }
        if (!is_string($hash) || !hash_equals($ruleset->sha256, strtolower($hash))) {
            abort(422, "Your {$ruleset->name} isn't the version this server ranks: update it in Settings > Custom rulesets.");
        }
    }

    /** Mods that play by themselves or for you: a custom ruleset's play with one isn't ranked. */
    const UNRANKED_MODS = ['AT', 'CN', 'RX', 'AP', 'AU'];

    /**
     * A custom ruleset's mods as TumThai sent them, cleaned: [{acronym, settings?}], acronyms of 1-8 letters or digits,
     * settings only scalar values, at most 20 mods.
     */
    public static function cleanMods(mixed $mods): array
    {
        $out = [];
        foreach (is_array($mods) ? array_slice($mods, 0, 20) : [] as $mod) {
            $mod = is_object($mod) ? (array) $mod : $mod;
            $acronym = is_array($mod) ? ($mod['acronym'] ?? null) : null;
            if (!is_string($acronym) || preg_match('/^[A-Za-z0-9]{1,8}$/', $acronym) !== 1) {
                continue;
            }
            $entry = ['acronym' => strtoupper($acronym)];
            $settings = [];
            foreach ((array) ($mod['settings'] ?? []) as $key => $value) {
                if (is_string($key) && strlen($key) <= 64 && (is_scalar($value) || $value === null)) {
                    $settings[$key] = is_string($value) ? mb_substr($value, 0, 100) : $value;
                }
            }
            if ($settings !== []) {
                $entry['settings'] = $settings;
            }
            $out[] = $entry;
        }

        return $out;
    }

    /** Whether a custom ruleset's play with these mods counts on the leaderboards (no automation mods). */
    public static function modsRanked(array $mods): bool
    {
        foreach ($mods as $mod) {
            $acronym = is_array($mod) ? ($mod['acronym'] ?? null) : (is_object($mod) ? ($mod->acronym ?? null) : $mod);
            if (is_string($acronym) && in_array(strtoupper($acronym), static::UNRANKED_MODS, true)) {
                return false;
            }
        }

        return true;
    }

    /** What TumThai (and anyone) reads: GET /api/v2/tomyum/rulesets. */
    public static function toApi(TomYumCustomRuleset $ruleset): array
    {
        return [
            'id' => $ruleset->id,
            'slug' => $ruleset->slug,
            'name' => $ruleset->name,
            'assembly' => $ruleset->assembly,
            'description' => $ruleset->description,
            'icon_url' => $ruleset->icon_url,
            'source_url' => $ruleset->source_url,
            'version' => $ruleset->version,
            'download_url' => $ruleset->download_url,
            'sha256' => $ruleset->sha256,
            'size' => $ruleset->size,
        ];
    }

    /**
     * rulesets.info's listing (cached an hour): each with slug, name, description, icon, direct_download_link,
     * can_download, archive, verified and status (latest_version, ...). Empty when it can't be read.
     */
    public static function listing(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('tomyum:rulesets-info');
        }

        return Cache::remember('tomyum:rulesets-info', 3600, function () {
            try {
                $response = Http::timeout(15)->acceptJson()->get(static::LISTING_URL);
            } catch (\Throwable) {
                return [];
            }

            return $response->successful() && is_array($response->json()) ? $response->json() : [];
        });
    }

    /**
     * Enables a rulesets.info ruleset, pinning its current release: a "latest" GitHub link is resolved to the release it
     * points at today, then the DLL is downloaded for its hash. Returns the ruleset, or throws with a message for staff.
     */
    public static function pin(string $slug, ?User $by): TomYumCustomRuleset
    {
        $entry = collect(static::listing(true))->first(fn ($r) => is_array($r) && strcasecmp((string) ($r['slug'] ?? ''), $slug) === 0);
        if ($entry === null) {
            throw new \RuntimeException("rulesets.info has no ruleset called {$slug}.");
        }
        $link = (string) ($entry['direct_download_link'] ?? '');
        if (!($entry['can_download'] ?? false) || !str_ends_with(strtolower(parse_url($link, PHP_URL_PATH) ?? ''), '.dll')) {
            throw new \RuntimeException("{$entry['name']} has no DLL to download on rulesets.info.");
        }

        [$url, $version, $source] = static::resolveRelease($link);
        try {
            $response = Http::timeout(60)->withOptions(['stream' => false])->get($url);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Couldn't download {$url}: {$e->getMessage()}");
        }
        $body = $response->body();
        if (!$response->successful() || strlen($body) === 0 || strlen($body) > static::MAX_BYTES || !str_starts_with($body, 'MZ')) {
            throw new \RuntimeException("{$url} didn't give a DLL (HTTP {$response->status()}).");
        }

        $assembly = preg_replace('/\.dll$/i', '', basename(parse_url($url, PHP_URL_PATH)));
        $existing = TomYumCustomRuleset::where('slug', $entry['slug'])->first();
        $ruleset = $existing ?? new TomYumCustomRuleset();
        if ($existing === null) {
            $ruleset->id = static::nextId((string) $entry['slug']);
        }
        $ruleset->slug = (string) $entry['slug'];
        $ruleset->name = mb_substr((string) ($entry['name'] ?? $entry['slug']), 0, 100);
        $ruleset->assembly = mb_substr($assembly, 0, 128);
        $ruleset->description = isset($entry['description']) ? mb_substr((string) $entry['description'], 0, 500) : null;
        $ruleset->icon_url = static::absolute($entry['icon'] ?? null);
        $ruleset->source_url = $source;
        $ruleset->version = $version ?? (isset($entry['status']['latest_version']) ? mb_substr((string) $entry['status']['latest_version'], 0, 64) : null);
        $ruleset->download_url = $url;
        $ruleset->sha256 = hash('sha256', $body);
        $ruleset->size = strlen($body);
        $ruleset->enabled = true;
        $ruleset->pinned_by = $by?->getKey();
        $ruleset->pinned_at = now();
        $ruleset->save();
        Cache::forget(static::CACHE_KEY);

        return $ruleset;
    }

    public static function disable(string $slug): ?TomYumCustomRuleset
    {
        $ruleset = TomYumCustomRuleset::where('slug', $slug)->first();
        if ($ruleset !== null) {
            $ruleset->enabled = false;
            $ruleset->save();
            Cache::forget(static::CACHE_KEY);
        }

        return $ruleset;
    }

    /**
     * A GitHub "releases/latest/download/<file>" link as the release it is now: [asset URL, tag, repository URL].
     * Any other link is pinned as it is (its version unknown).
     *
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private static function resolveRelease(string $link): array
    {
        if (preg_match('#^https://github\.com/([^/]+)/([^/]+)/releases/latest/download/([^/?]+)$#', $link, $m) !== 1) {
            return [$link, null, null];
        }
        [, $owner, $repo, $file] = $m;
        $source = "https://github.com/{$owner}/{$repo}";
        try {
            $release = Http::timeout(15)->acceptJson()->withHeaders(['User-Agent' => 'osu-web (TomYum)'])
                ->get("https://api.github.com/repos/{$owner}/{$repo}/releases/latest");
        } catch (\Throwable) {
            return [$link, null, $source];
        }
        foreach ($release->json('assets') ?? [] as $asset) {
            if (($asset['name'] ?? null) === $file && is_string($asset['browser_download_url'] ?? null)) {
                return [$asset['browser_download_url'], is_string($release->json('tag_name')) ? mb_substr($release->json('tag_name'), 0, 64) : null, $source];
            }
        }

        return [$link, null, $source];
    }

    private static function nextId(string $slug): int
    {
        if (isset(static::FIXED_IDS[strtolower($slug)])) {
            return static::FIXED_IDS[strtolower($slug)];
        }
        $taken = TomYumCustomRuleset::pluck('id')->all();
        for ($id = static::FIRST_FREE_ID; in_array($id, $taken, true) || in_array($id, static::FIXED_IDS, true); $id++) {
        }

        return $id;
    }

    private static function absolute(mixed $url): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }

        return mb_substr(str_starts_with($url, 'http') ? $url : 'https://rulesets.info/'.ltrim($url, '/'), 0, 500);
    }
}
