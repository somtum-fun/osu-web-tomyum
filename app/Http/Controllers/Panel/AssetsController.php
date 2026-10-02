<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumAssets;
use Auth;
use Request;

/**
 * Admin and TC panels: the assets host's images (badges, tournament banners, medal icons), at
 * osu!'s paths and as osu!'s pairs: name.png and name@2x.png. Browse the folders, upload (the @2x
 * alone is enough: the normal one is made from it), copy from osu! by link, rename, remove. Names
 * are the ones given, never made up. Medal icons are the admins' only.
 */
class AssetsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseSharedPanel(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $folder = $this->folder((string) Request::input('folder', 'profile-badges'));
        [$listing, $problem] = $this->assets()->list($folder);

        return ext_view('panel.admin.assets', [
            'folder' => $folder,
            'root' => TomYumAssets::rootOf($folder),
            'roots' => $this->roots(),
            'listing' => $listing,
            'problem' => $problem,
        ]);
    }

    public function store()
    {
        $folder = $this->folder((string) Request::input('folder'));
        $root = TomYumAssets::rootOf($folder);
        $sub = trim((string) Request::input('subfolder'), " /");
        if ($sub !== '') {
            $folder .= "/{$sub}";
        }

        $normal = Request::file('normal');
        $retina = Request::file('retina');
        $source = $retina ?? $normal;
        if ($source === null) {
            return $this->back($folder, 'error', 'Choose the image: the @2x one (double size), the normal one, or both.');
        }
        // Banners are .jpg (osu!'s are, and the site asks for them by that name); others keep their own kind.
        $extension = $root === 'tournament-banners' ? 'jpg' : strtolower($source->getClientOriginalExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $name = static::plainName((string) Request::input('name'))
            ?? static::plainName(pathinfo($source->getClientOriginalName(), PATHINFO_FILENAME));
        if ($name === null) {
            return $this->back($folder, 'error', 'Give it a name: letters, digits, - and _ (like ndc-2023).');
        }

        $path = "{$folder}/{$name}.{$extension}";
        if (($problem = $this->assets()->save($path, $normal, $retina)) !== null) {
            return $this->back($folder, 'error', $problem);
        }

        return $this->back($folder, 'success', "Saved {$name}.{$extension}".($retina !== null && $normal === null ? ' (the normal size made from the @2x one).' : '.'));
    }

    public function import()
    {
        $folder = $this->folder((string) Request::input('folder'));
        [$copied, $problems] = $this->assets()->import((string) Request::input('links'));
        $message = count($copied) === 0 ? '' : 'Copied '.implode(', ', array_map('basename', $copied)).'.';
        if (count($problems) > 0) {
            $message = trim($message.' Not copied: '.implode(' ', $problems));
        }
        // Where the first one went, so it shows.
        $to = count($copied) > 0 ? dirname($copied[0]) : $folder;

        return $this->back($to, count($copied) > 0 ? 'success' : 'error', $message === '' ? 'Nothing to copy.' : $message);
    }

    public function rename()
    {
        $path = (string) Request::input('path');
        $this->folder(dirname($path));
        [$newPath, $problem] = $this->assets()->rename($path, (string) Request::input('name'));

        return $problem === null
            ? $this->back(dirname($path), 'success', 'Renamed to '.basename($newPath).' (badges and banners using it follow).')
            : $this->back(dirname($path), 'error', $problem);
    }

    public function destroy()
    {
        $path = (string) Request::input('path');
        $this->folder(dirname($path));
        $problem = $this->assets()->remove($path);

        return $problem === null
            ? $this->back(dirname($path), 'success', 'Removed '.basename($path).' (both sizes).')
            : $this->back(dirname($path), 'error', $problem);
    }

    /** A name made plain (letters, digits, - and _), or null when nothing's left. */
    public static function plainName(string $name): ?string
    {
        $name = preg_replace('/\.(png|jpe?g|gif|webp)$/i', '', trim($name));
        $name = preg_replace('/@2x$/i', '', $name);
        $name = trim(preg_replace('/[^A-Za-z0-9_-]/', '', str_replace(' ', '-', $name)), '-_');

        return $name === '' || strlen($name) > 80 ? null : $name;
    }

    /** The folders this user may manage: the TC has badges and banners; admins medal icons too. */
    private function roots(): array
    {
        return array_filter(
            TomYumAssets::ROOTS,
            fn ($root) => $root !== 'medals/client' || static::canUseAdmin(Auth::user()),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** A folder this user may manage (a plain path under one of their roots), or 404. */
    private function folder(string $folder): string
    {
        $folder = trim($folder, '/');
        $root = TomYumAssets::rootOf($folder);
        abort_if($root === null || !isset($this->roots()[$root]), 404);
        abort_unless(preg_match('#^[A-Za-z0-9][A-Za-z0-9._@-]*(/[A-Za-z0-9][A-Za-z0-9._@-]*)*$#', $folder) === 1, 404);

        return $folder;
    }

    private function assets(): TomYumAssets
    {
        return new TomYumAssets(Auth::user());
    }

    private function back(string $folder, string $type, string $message)
    {
        return redirect(route(static::sharedPanelPrefix().'assets.index', ['folder' => $folder]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
