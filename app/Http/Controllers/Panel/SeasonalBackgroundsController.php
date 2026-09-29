<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumSeasonal;
use App\Models\User;
use App\Models\UserContestEntry;
use Auth;
use Carbon\CarbonImmutable;
use Request;

/**
 * Admin panel: seasonal backgrounds, the menu backgrounds lazer and osu!stable show until the season
 * ends. Upload images (credited to their artist), hide or remove them, and set the end date.
 */
class SeasonalBackgroundsController extends Controller
{
    private const MAX_BYTES = 15 * 1024 * 1024;

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
        $contestId = TomYumSeasonal::contestId();
        $entries = $contestId === null
            ? collect()
            : UserContestEntry::where('contest_id', $contestId)->with('user')->orderByDesc('id')->get();

        return ext_view('panel.admin.seasonal_backgrounds', [
            'entries' => $entries,
            'endsAt' => TomYumSeasonal::endsAt(),
        ]);
    }

    public function store()
    {
        $file = Request::file('image');
        if ($file === null || !$file->isValid()) {
            return $this->back('error', 'Choose an image to upload.');
        }
        if ($file->getSize() > static::MAX_BYTES) {
            return $this->back('error', 'That image is over 15 MB.');
        }
        if (!in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $this->back('error', 'Use a JPEG, PNG or WebP image.');
        }

        $artistName = trim((string) Request::input('artist'));
        $artist = $artistName === '' ? Auth::user() : User::lookup($artistName, null, true);
        if ($artist === null) {
            return $this->back('error', "There's no player called \"{$artistName}\".");
        }

        $entry = UserContestEntry::upload($file, $artist, TomYumSeasonal::contest());
        try {
            TomYumSeasonal::makeClientCopy($entry, $file->getRealPath());
        } catch (\Throwable $e) {
            $entry->delete();

            return $this->back('error', 'That image couldn\'t be read: '.$e->getMessage().'.');
        }
        $entry->update(['show_in_client' => true]);

        // A first background without an end date would never show: give the season a month.
        if (TomYumSeasonal::endsAt() === null) {
            TomYumSeasonal::setEndsAt(CarbonImmutable::now()->addMonth()->startOfDay());
        }
        $this->staffLog('seasonal.add', $entry->getKey(), "Added a seasonal background by {$artist->username}");

        return $this->back('success', 'Added. The games pick it up the next time they start.');
    }

    public function toggle(string $entry)
    {
        $model = $this->entry($entry);
        $model->update(['show_in_client' => !$model->show_in_client]);

        return $this->back('success', $model->show_in_client ? 'Shown again.' : 'Hidden.');
    }

    public function destroy(string $entry)
    {
        $model = $this->entry($entry);
        $model->delete();
        $this->staffLog('seasonal.remove', $model->getKey(), 'Removed a seasonal background');

        return $this->back('success', 'Removed.');
    }

    public function settings()
    {
        try {
            $endsAt = CarbonImmutable::parse((string) Request::input('ends_at'), 'UTC')->endOfDay();
        } catch (\Throwable) {
            return $this->back('error', 'That isn\'t a date.');
        }
        TomYumSeasonal::setEndsAt($endsAt);
        $this->staffLog('seasonal.ends_at', null, "Seasonal backgrounds now end {$endsAt->toDateString()}");

        return $this->back('success', $endsAt->isPast()
            ? 'Saved. That date has passed, so the games show their usual backgrounds.'
            : 'Saved.');
    }

    private function entry(string $id): UserContestEntry
    {
        return UserContestEntry::where('contest_id', TomYumSeasonal::contestId())->findOrFail((int) $id);
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.seasonal-backgrounds.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
