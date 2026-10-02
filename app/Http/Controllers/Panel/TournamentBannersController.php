<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumAssets;
use App\Models\ProfileBanner;
use App\Models\Tournament;
use App\Models\TournamentBanner;
use App\Models\User;
use Auth;
use Carbon\CarbonImmutable;
use DB;
use Request;

/**
 * Admin and TC panels: tournament banners on profiles (osu-web's own feature: the banner under a
 * player's header, linking to the tournament). Like osu!'s, a tournament's banners are a folder on
 * the assets host (tournament-banners/official/owc2025/profile/), and every image in it is one of
 * them, at normal and double size (supporter_CA.jpg, supporter_CA@2x.jpg; winner_CA.jpg, …): a player
 * is given the tournament and which of its images is theirs. The images are managed here or on the
 * Assets page, named as given. Banners made before (one uploaded image for everyone) keep working,
 * and move to the assets host with a click.
 */
class TournamentBannersController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseSharedPanel(Auth::user())); // TomYum: the admin's and the TC's

            return $next($request);
        });
    }

    public function index()
    {
        $banners = TournamentBanner::with('tournament')->orderByDesc('tournament_id')->get();
        $holders = ProfileBanner::with('user')->whereIn('tournament_id', $banners->pluck('tournament_id'))->get()
            ->groupBy('tournament_id');

        // Each banner's images (its folder's), for showing and giving.
        $images = [];
        $problems = [];
        foreach ($banners as $banner) {
            $folder = static::folderOf($banner);
            if ($folder === null) {
                continue;
            }
            [$listing, $problem] = $this->assets()->list("tournament-banners/{$folder}");
            $images[$banner->getKey()] = $listing['images'] ?? [];
            if ($problem !== null) {
                $problems[$banner->getKey()] = $problem;
            }
        }

        return ext_view('panel.admin.tournament_banners', [
            'banners' => $banners,
            'holders' => $holders,
            'images' => $images,
            'problems' => $problems,
        ]);
    }

    public function store()
    {
        $name = trim((string) Request::input('name'));
        if ($name === '' || mb_strlen($name) > 255) {
            return $this->back('error', 'A banner needs its tournament\'s name (up to 255 characters).');
        }
        $infoUrl = presence(trim((string) Request::input('info_url')));
        if ($infoUrl !== null && !preg_match('#^https?://#', $infoUrl)) {
            return $this->back('error', 'The tournament\'s link should be a web address (https://…).');
        }
        $folder = static::plainFolder((string) Request::input('folder'));
        if ($folder === null) {
            return $this->back('error', 'Give the images\' folder: like official/owc2025/profile (letters, digits, - and _, with / between folders).');
        }
        $date = presence((string) Request::input('date'));
        try {
            $when = $date === null ? CarbonImmutable::now() : CarbonImmutable::parse($date)->endOfDay();
        } catch (\Throwable $e) {
            return $this->back('error', 'That date isn\'t one.');
        }

        $tournament = DB::transaction(function () use ($name, $infoUrl, $when, $folder) {
            // Held (and over) on the day given: the tournaments page lists it with the previous ones.
            $tournament = Tournament::create([
                'name' => $name,
                'description' => '',
                'play_mode' => (int) Request::input('mode', 0),
                'signup_open' => $when,
                'signup_close' => $when,
                'start_date' => $when,
                'end_date' => $when,
                'info_url' => $infoUrl,
            ]);
            TournamentBanner::create([
                'tournament_id' => $tournament->getKey(),
                'is_active' => true,
                'banner_url_prefix' => static::prefixFor($folder),
            ]);

            return $tournament;
        });
        $this->staffLog('banner.add', $tournament->getKey(), "Made the tournament banner \"{$name}\" (tournament-banners/{$folder})");

        return $this->back('success', "Made \"{$name}\". Add its images (upload, or copy osu!'s), then give them to players.", "banner-{$tournament->getKey()}");
    }

    /** One of a banner's images, uploaded: the @2x one (the normal is made from it), the normal, or both. */
    public function upload(string $banner)
    {
        $row = TournamentBanner::with('tournament')->findOrFail((int) $banner);
        $folder = static::folderOf($row);
        if ($folder === null) {
            return $this->back('error', 'Move this banner to the assets host first.', "banner-{$row->getKey()}");
        }
        $retina = Request::file('retina');
        $normal = Request::file('normal');
        $source = $retina ?? $normal;
        if ($source === null) {
            return $this->back('error', 'Choose the image: the @2x one (2000×120), the normal one, or both.', "banner-{$row->getKey()}");
        }
        $name = AssetsController::plainName((string) Request::input('name'))
            ?? AssetsController::plainName(pathinfo($source->getClientOriginalName(), PATHINFO_FILENAME));
        if ($name === null) {
            return $this->back('error', 'Give the image a name: letters, digits, - and _ (like winner_CA or banner).', "banner-{$row->getKey()}");
        }

        $problem = $this->assets()->save("tournament-banners/{$folder}/{$name}.jpg", $normal, $retina);

        return $problem === null
            ? $this->back('success', "Added {$name}.jpg to \"{$row->tournament?->name}\".", "banner-{$row->getKey()}")
            : $this->back('error', $problem, "banner-{$row->getKey()}");
    }

    /** Banner images by link (osu!'s or any, one per line), copied into this banner's folder at both sizes. */
    public function copy(string $banner)
    {
        $row = TournamentBanner::with('tournament')->findOrFail((int) $banner);
        $folder = static::folderOf($row);
        if ($folder === null) {
            return $this->back('error', 'Move this banner to the assets host first.', "banner-{$row->getKey()}");
        }

        $links = collect(preg_split('/\r?\n/', (string) Request::input('links')))
            ->map(fn ($link) => trim($link))
            ->filter()
            ->unique()
            ->take(100);
        $copied = [];
        $problems = [];
        foreach ($links as $link) {
            // The link's own name (supporter_CA.jpg, from either size's link).
            $file = basename(preg_replace('/[?#].*$/', '', $link));
            $file = preg_replace('/@2x(\.jpg)$/i', '$1', $file);
            if (!preg_match('/^[A-Za-z0-9_-]+\.jpg$/i', $file)) {
                $problems[] = "{$link}: not a banner image (….jpg).";
                continue;
            }
            [$sources, $problem] = $this->download($link);
            if ($problem === null) {
                $problem = $this->assets()->saveBytes("tournament-banners/{$folder}/{$file}", $sources[0], $sources[1]);
            }
            if ($problem === null) {
                $copied[] = $file;
            } else {
                $problems[] = "{$link}: {$problem}";
            }
        }

        $message = trim((count($copied) > 0 ? 'Copied '.implode(', ', $copied).'.' : '').(count($problems) > 0 ? ' Not copied: '.implode(' ', $problems) : ''));

        return $this->back(count($copied) > 0 ? 'success' : 'error', $message === '' ? 'Nothing to copy.' : $message, "banner-{$row->getKey()}");
    }

    /** A banner made before (one uploaded image) to the assets host: its image becomes {folder}/banner.jpg. */
    public function move(string $banner)
    {
        $row = TournamentBanner::with('tournament')->findOrFail((int) $banner);
        if (static::folderOf($row) !== null) {
            return $this->back('success', 'It\'s on the assets host already.', "banner-{$row->getKey()}");
        }
        $folder = static::plainFolder((string) Request::input('folder'));
        if ($folder === null) {
            return $this->back('error', 'Give the images\' folder: like official/owc2025/profile (letters, digits, - and _, with / between folders).', "banner-{$row->getKey()}");
        }

        // The old image is the prefix itself (…/uploads/tournament-banners/{id}/banner) with .jpg.
        $relative = ltrim((string) parse_url($row->banner_url_prefix, PHP_URL_PATH), '/');
        $file = public_path("{$relative}.jpg");
        if (!str_starts_with($relative, 'uploads/tournament-banners/') || !is_file($file)) {
            return $this->back('error', 'Its image isn\'t on the website any more: make the banner again on the assets host.', "banner-{$row->getKey()}");
        }
        $retina = preg_replace('/\.jpg$/', '@2x.jpg', $file);
        $problem = $this->assets()->saveBytes(
            "tournament-banners/{$folder}/banner.jpg",
            file_get_contents($file),
            is_file($retina) ? file_get_contents($retina) : null,
        );
        if ($problem !== null) {
            return $this->back('error', $problem, "banner-{$row->getKey()}");
        }

        DB::transaction(function () use ($row, $folder) {
            $row->update(['banner_url_prefix' => static::prefixFor($folder)]);
            ProfileBanner::where('tournament_id', $row->getKey())->update(['country_acronym' => 'banner']);
        });
        foreach (glob(public_path("uploads/tournament-banners/{$row->getKey()}/*.jpg")) ?: [] as $old) {
            @unlink($old);
        }
        @rmdir(public_path("uploads/tournament-banners/{$row->getKey()}"));
        $this->staffLog('banner.image', $row->getKey(), "Moved the tournament banner \"{$row->tournament?->name}\" to tournament-banners/{$folder}");

        return $this->back('success', "Moved: its image is tournament-banners/{$folder}/banner.jpg now, and everyone who has it keeps it.", "banner-{$row->getKey()}");
    }

    public function toggle(string $banner)
    {
        $row = TournamentBanner::with('tournament')->findOrFail((int) $banner);
        $row->update(['is_active' => !$row->is_active]);
        $name = $row->tournament?->name ?? "#{$row->getKey()}";
        $this->staffLog('banner.toggle', $row->getKey(), ($row->is_active ? 'Showed' : 'Hid')." the tournament banner \"{$name}\"");

        return $this->back('success', $row->is_active ? "\"{$name}\" shows on profiles again." : "\"{$name}\" is hidden from profiles (kept for later).", "banner-{$row->getKey()}");
    }

    public function destroy(string $banner)
    {
        $row = TournamentBanner::with('tournament')->findOrFail((int) $banner);
        $name = $row->tournament?->name ?? "#{$row->getKey()}";
        DB::transaction(function () use ($row) {
            ProfileBanner::where('tournament_id', $row->getKey())->delete();
            $row->delete();
            $row->tournament?->delete();
        });
        // A banner made before had its image with the website's uploads; the assets host's stay (the Assets page removes them).
        foreach (glob(public_path("uploads/tournament-banners/{$row->getKey()}/*.jpg")) ?: [] as $file) {
            @unlink($file);
        }
        @rmdir(public_path("uploads/tournament-banners/{$row->getKey()}"));
        $this->staffLog('banner.remove', $row->getKey(), "Removed the tournament banner \"{$name}\" (and took it from everyone)");

        return $this->back('success', "Removed \"{$name}\" (and took it from everyone who had it). Its images stay on the Assets page.");
    }

    public function give(string $banner)
    {
        $row = TournamentBanner::with('tournament')->findOrFail((int) $banner);
        $name = trim((string) Request::input('user'));
        $user = $name === '' ? null : (ctype_digit($name) ? User::find((int) $name) : User::lookup($name, 'username'));
        if ($user === null) {
            return $this->back('error', "There's no player called \"{$name}\".", "banner-{$row->getKey()}");
        }

        // Which of its images: one of its folder's (a banner made before has just the one, '').
        $folder = static::folderOf($row);
        $variant = '';
        if ($folder !== null) {
            $available = array_map(
                fn ($image) => pathinfo($image['name'], PATHINFO_FILENAME),
                $this->assets()->list("tournament-banners/{$folder}")[0]['images'] ?? [],
            );
            $variant = (string) Request::input('image');
            // Their country's: the image whose name ends with _{their country} (supporter_CA for a Canadian).
            if ($variant === '*country') {
                $country = $user->country_acronym;
                $variant = collect($available)->first(fn ($image) => str_ends_with($image, "_{$country}")) ?? '';
                if ($variant === '') {
                    return $this->back('error', "None of its images is for {$user->username}'s country ({$country}).", "banner-{$row->getKey()}");
                }
            } elseif (!in_array($variant, $available, true)) {
                return $this->back('error', 'Pick which of its images is theirs.', "banner-{$row->getKey()}");
            }
        }

        $existing = ProfileBanner::where('tournament_id', $row->getKey())->where('user_id', $user->getKey())->first();
        if ($existing !== null) {
            if ($existing->country_acronym === $variant) {
                return $this->back('success', "{$user->username} already has it.", "banner-{$row->getKey()}");
            }
            $existing->update(['country_acronym' => $variant]);
        } else {
            ProfileBanner::create(['user_id' => $user->getKey(), 'tournament_id' => $row->getKey(), 'country_acronym' => $variant]);
        }
        $which = $variant === '' ? '' : " ({$variant})";
        $this->staffLog('user.banner_add', $user->getKey(), "Gave {$user->username} the tournament banner \"{$row->tournament?->name}\"{$which}");

        return $this->back('success', "{$user->username} has the \"{$row->tournament?->name}\" banner{$which} now.", "banner-{$row->getKey()}");
    }

    public function take(string $banner, string $user)
    {
        $row = TournamentBanner::with('tournament')->findOrFail((int) $banner);
        $target = User::findOrFail((int) $user);
        ProfileBanner::where('tournament_id', $row->getKey())->where('user_id', $target->getKey())->delete();
        $this->staffLog('user.banner_remove', $target->getKey(), "Took the tournament banner \"{$row->tournament?->name}\" from {$target->username}");

        return $this->back('success', "Took it from {$target->username}.", "banner-{$row->getKey()}");
    }

    /** A banner's folder under tournament-banners/ on the assets host; null for one made before (uploads). */
    public static function folderOf(TournamentBanner $banner): ?string
    {
        $base = TomYumAssets::baseUrl().'/tournament-banners/';
        $prefix = $banner->banner_url_prefix;

        return str_starts_with($prefix, $base) && str_ends_with($prefix, '/') && strlen($prefix) > strlen($base) + 1
            ? substr($prefix, strlen($base), -1)
            : null;
    }

    /** A folder made plain (official/owc2025/profile), or null. */
    public static function plainFolder(string $folder): ?string
    {
        $folder = trim(str_replace('\\', '/', trim($folder)), '/');
        $folder = preg_replace('#^tournament-banners/#', '', $folder);

        return strlen($folder) <= 150 && preg_match('#^[A-Za-z0-9][A-Za-z0-9_-]*(/[A-Za-z0-9][A-Za-z0-9_-]*){0,5}$#', $folder) === 1
            ? $folder
            : null;
    }

    private static function prefixFor(string $folder): string
    {
        return TomYumAssets::baseUrl()."/tournament-banners/{$folder}/";
    }

    /** An image link's two sizes (the given one, and its @2x or normal one): [[normal, retina], null] or [null, the problem]. */
    private function download(string $link): array
    {
        if (!preg_match('#^https?://#i', $link)) {
            return [null, 'not a web address.'];
        }
        $normalUrl = preg_replace('/@2x(\.jpg)(\?.*)?$/i', '$1$2', $link);
        $retinaUrl = preg_replace('/(\.jpg)(\?.*)?$/i', '@2x$1$2', $normalUrl);
        $fetch = function (string $url): ?string {
            try {
                $response = \Http::timeout(20)->get($url);
            } catch (\Throwable) {
                return null;
            }
            $body = $response->body();

            return $response->successful() && strlen($body) <= 10 * 1024 * 1024 && @getimagesizefromstring($body) !== false ? $body : null;
        };
        $normal = $fetch($normalUrl);
        $retina = $fetch($retinaUrl);

        return $normal === null && $retina === null ? [null, 'no image there.'] : [[$normal, $retina], null];
    }

    private function assets(): TomYumAssets
    {
        return new TomYumAssets(Auth::user());
    }

    private function back(string $type, string $message, ?string $anchor = null)
    {
        return redirect(route(static::sharedPanelPrefix().'tournament-banners.index').($anchor === null ? '' : "#{$anchor}"))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
