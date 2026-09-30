<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Models\ProfileBanner;
use App\Models\Tournament;
use App\Models\TournamentBanner;
use App\Models\User;
use Auth;
use Carbon\CarbonImmutable;
use DB;
use Http;
use Request;

/**
 * Admin panel: tournament banners on profiles (osu-web's own feature: the banner under a player's
 * header, linking to the tournament). A banner is a tournament (shown with the past ones on the
 * tournaments page) with one image for everyone who gets it, stored with the site's uploads at
 * normal and double size (tournament-banners/{id}/banner.jpg, banner@2x.jpg).
 */
class TournamentBannersController extends Controller
{
    /** Biggest image taken (uploaded or fetched). */
    private const MAX_BYTES = 10 * 1024 * 1024;

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
        $banners = TournamentBanner::with('tournament')->orderByDesc('tournament_id')->get();
        $holders = ProfileBanner::with('user')->whereIn('tournament_id', $banners->pluck('tournament_id'))->get()
            ->groupBy('tournament_id');

        return ext_view('panel.admin.tournament_banners', ['banners' => $banners, 'holders' => $holders]);
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
        $date = presence((string) Request::input('date'));
        try {
            $when = $date === null ? CarbonImmutable::now() : CarbonImmutable::parse($date)->endOfDay();
        } catch (\Throwable $e) {
            return $this->back('error', 'That date isn\'t one.');
        }

        [$image, $problem] = $this->image();
        if ($image === null) {
            return $this->back('error', $problem);
        }

        $tournament = DB::transaction(function () use ($name, $infoUrl, $when) {
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
                'banner_url_prefix' => url("/uploads/tournament-banners/{$tournament->getKey()}/banner"),
            ]);

            return $tournament;
        });

        if (!$this->save($tournament->getKey(), $image)) {
            DB::transaction(function () use ($tournament) {
                TournamentBanner::whereKey($tournament->getKey())->delete();
                $tournament->delete();
            });

            return $this->back('error', 'That isn\'t an image the site can read (JPEG, PNG, WebP or GIF).');
        }
        $this->staffLog('banner.add', $tournament->getKey(), "Made the tournament banner \"{$name}\"");

        return $this->back('success', "Made the banner for \"{$name}\". Give it to players below.");
    }

    public function toggle(string $banner)
    {
        $row = TournamentBanner::with('tournament')->findOrFail((int) $banner);
        $row->update(['is_active' => !$row->is_active]);
        $name = $row->tournament?->name ?? "#{$row->getKey()}";
        $this->staffLog('banner.toggle', $row->getKey(), ($row->is_active ? 'Showed' : 'Hid')." the tournament banner \"{$name}\"");

        return $this->back('success', $row->is_active ? "\"{$name}\" shows on profiles again." : "\"{$name}\" is hidden from profiles (kept for later).");
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
        foreach (['banner.jpg', 'banner@2x.jpg'] as $file) {
            @unlink(public_path("uploads/tournament-banners/{$row->getKey()}/{$file}"));
        }
        @rmdir(public_path("uploads/tournament-banners/{$row->getKey()}"));
        $this->staffLog('banner.remove', $row->getKey(), "Removed the tournament banner \"{$name}\" (and took it from everyone)");

        return $this->back('success', "Removed \"{$name}\" (and took it from everyone who had it).");
    }

    public function give(string $banner)
    {
        $row = TournamentBanner::with('tournament')->findOrFail((int) $banner);
        $name = trim((string) Request::input('user'));
        $user = $name === '' ? null : (ctype_digit($name) ? User::find((int) $name) : User::lookup($name, 'username'));
        if ($user === null) {
            return $this->back('error', "There's no player called \"{$name}\".");
        }
        if (ProfileBanner::where('tournament_id', $row->getKey())->where('user_id', $user->getKey())->exists()) {
            return $this->back('success', "{$user->username} already has it.");
        }

        // One image for everyone: the "country" part of the image's name is empty.
        ProfileBanner::create(['user_id' => $user->getKey(), 'tournament_id' => $row->getKey(), 'country_acronym' => '']);
        $this->staffLog('user.banner_add', $user->getKey(), "Gave {$user->username} the tournament banner \"{$row->tournament?->name}\"");

        return $this->back('success', "{$user->username} has the \"{$row->tournament?->name}\" banner now.");
    }

    public function take(string $banner, string $user)
    {
        $row = TournamentBanner::with('tournament')->findOrFail((int) $banner);
        $target = User::findOrFail((int) $user);
        ProfileBanner::where('tournament_id', $row->getKey())->where('user_id', $target->getKey())->delete();
        $this->staffLog('user.banner_remove', $target->getKey(), "Took the tournament banner \"{$row->tournament?->name}\" from {$target->username}");

        return $this->back('success', "Took it from {$target->username}.");
    }

    /** The uploaded image, or the one at the given address: [bytes, null] or [null, why not]. */
    private function image(): array
    {
        $file = Request::file('image');
        if ($file !== null) {
            if (!$file->isValid() || $file->getSize() > static::MAX_BYTES) {
                return [null, 'The image didn\'t upload (it can be up to 10 MB).'];
            }

            return [file_get_contents($file->getRealPath()), null];
        }

        $url = presence(trim((string) Request::input('image_url')));
        if ($url === null) {
            return [null, 'Upload an image, or give the address of one.'];
        }
        if (!preg_match('#^https?://#', $url)) {
            return [null, 'The image address should be a web address (https://…).'];
        }
        try {
            $response = Http::timeout(20)->get($url);
        } catch (\Throwable $e) {
            return [null, 'Couldn\'t fetch the image from that address.'];
        }
        if (!$response->successful() || strlen($response->body()) > static::MAX_BYTES) {
            return [null, 'Couldn\'t fetch the image from that address (or it\'s over 10 MB).'];
        }

        return [$response->body(), null];
    }

    /** Stores the banner as JPEG at double size (as given) and normal size (half); false when it isn't an image. */
    private function save(int $id, string $bytes): bool
    {
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return false;
        }
        $dir = public_path("uploads/tournament-banners/{$id}");
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        imagejpeg($image, "{$dir}/banner@2x.jpg", 90);
        $half = imagescale($image, max(1, intdiv($width, 2)), max(1, intdiv($height, 2)));
        imagejpeg($half !== false ? $half : $image, "{$dir}/banner.jpg", 90);

        return true;
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.tournament-banners.index'))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
