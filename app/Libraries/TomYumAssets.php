<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Libraries;

use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * TomYum: the assets host's images (assets.<domain>, kept by the game server at osu!'s paths:
 * profile-badges/ndc-2023.png and its @2x, tournament-banners/official/owc2025/profile/…): their
 * addresses, and the game server's admin API for them (TomYum's AdminAssetEndpoints).
 */
class TomYumAssets
{
    public const ROOTS = [
        'profile-badges' => ['Badges', 'fa-certificate', '86×40, @2x 172×80'],
        'tournament-banners' => ['Tournament banners', 'fa-trophy', '1000×60 (or so), @2x 2000×120; .jpg'],
        'medals/client' => ['Medal icons', 'fa-medal', '@2x 230×251'],
    ];

    public function __construct(private ?User $actor = null)
    {
    }

    /** The assets host (https://assets.<domain>), from the badge images' address. */
    public static function baseUrl(): string
    {
        return $GLOBALS['cfg']['tomyum']['assets_url'];
    }

    public static function url(string $path): string
    {
        // Encoded part by part, but @ stays as it is (…@2x.png, like osu!'s).
        return static::baseUrl().'/'.str_replace('%40', '@', implode('/', array_map('rawurlencode', explode('/', $path))));
    }

    /** The @2x one's path or address: …/ndc-2023.png → …/ndc-2023@2x.png. */
    public static function retina(string $path): string
    {
        return preg_replace('/(\.[A-Za-z0-9]+)$/', '@2x$1', $path, 1);
    }

    /** The root folder a path is in (profile-badges, tournament-banners, medals/client), or null. */
    public static function rootOf(string $path): ?string
    {
        foreach (array_keys(static::ROOTS) as $root) {
            if ($path === $root || str_starts_with($path, "{$root}/")) {
                return $root;
            }
        }

        return null;
    }

    /** A folder's subfolders and image pairs; [listing, null] or [null, the problem]. */
    public function list(string $folder): array
    {
        try {
            $response = $this->api()->get('/assets', ['folder' => $folder]);
        } catch (\Throwable $e) {
            return [null, 'The game server didn\'t answer.'];
        }

        return $response->successful()
            ? [$response->json('folder'), null]
            : [null, $response->json('error') ?? "The game server said no (HTTP {$response->status()})."];
    }

    /**
     * Saves a pair at $path (the normal one's, like profile-badges/ndc-2023.png) from uploads: both,
     * or only the @2x one (the normal is made from it), or only the normal. The problem, if any.
     */
    public function save(string $path, ?UploadedFile $normal, ?UploadedFile $retina): ?string
    {
        foreach ([$normal, $retina] as $file) {
            if ($file !== null && (!$file->isValid() || $file->getSize() > 10 * 1024 * 1024)) {
                return 'An image didn\'t upload (each can be up to 10 MB).';
            }
        }
        $read = fn (?UploadedFile $file) => $file === null ? null : file_get_contents($file->getRealPath());

        return $this->saveBytes($path, $read($normal), $read($retina));
    }

    /** Saves a pair from the images' bytes (either may be null, like save). The problem, if any. */
    public function saveBytes(string $path, ?string $normal, ?string $retina): ?string
    {
        return $this->problem(fn () => $this->api()->post('/assets', [
            'path' => $path,
            'normal' => $normal === null ? null : base64_encode($normal),
            'retina' => $retina === null ? null : base64_encode($retina),
        ]));
    }

    public function remove(string $path): ?string
    {
        return $this->problem(fn () => $this->api()->post('/assets/remove', ['path' => $path]));
    }

    /** Renames a pair; what uses it follows (badges, banners). [the new path, null] or [null, the problem]. */
    public function rename(string $path, string $name): array
    {
        try {
            $response = $this->api()->post('/assets/rename', ['path' => $path, 'name' => $name]);
        } catch (\Throwable $e) {
            return [null, 'The game server didn\'t answer.'];
        }

        return $response->successful() ? [$response->json('path'), null] : [null, $response->json('error') ?? 'The game server said no.'];
    }

    /** Copies osu! asset links here (both sizes): [copied paths, problems]. */
    public function import(string $links): array
    {
        try {
            $response = $this->api()->post('/assets/import', ['links' => $links]);
        } catch (\Throwable $e) {
            return [[], ['The game server didn\'t answer.']];
        }

        return $response->successful()
            ? [$response->json('copied') ?? [], $response->json('problems') ?? []]
            : [[], [$response->json('error') ?? 'The game server said no.']];
    }

    private function api(): TomYumAdmin
    {
        return new TomYumAdmin($this->actor);
    }

    private function problem(\Closure $call): ?string
    {
        try {
            $response = $call();
        } catch (\Throwable $e) {
            return 'The game server didn\'t answer.';
        }

        return $response->successful() ? null : ($response->json('error') ?? "The game server said no (HTTP {$response->status()}).");
    }
}
