<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Contest;
use App\Models\Count;
use App\Models\UserContestEntry;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * TomYum: seasonal backgrounds (the menu backgrounds of lazer and osu!stable) set from the admin
 * panel. osu-web shows the entries of a contest (SEASONAL_CONTEST_ID) until SEASONAL_ENDS_AT; here
 * the contest is made on first use and both are kept in osu_counts, so no env change is needed.
 * Each image also gets the "_opt.jpg" copy the clients load (ppy makes it with separate tooling).
 */
class TomYumSeasonal
{
    private const CONTEST_COUNT = 'tomyum_seasonal_contest';
    private const ENDS_AT_COUNT = 'tomyum_seasonal_ends_at';

    /** The longest side of the clients' copy. */
    private const MAX_SIZE = 2560;

    public static function contestId(): ?int
    {
        return $GLOBALS['cfg']['osu']['seasonal']['contest_id'] ?? Count::find(static::CONTEST_COUNT)?->count;
    }

    /** The contest holding the backgrounds, made hidden on first use. */
    public static function contest(): Contest
    {
        $contest = Contest::find(static::contestId());
        if ($contest !== null) {
            return $contest;
        }

        $now = Carbon::now();
        $contest = Contest::create([
            'name' => 'Seasonal backgrounds',
            'description_enter' => 'Menu backgrounds shown in the game (set from the admin panel).',
            'description_voting' => '',
            'type' => 'art',
            'max_entries' => 100,
            'max_votes' => 0,
            'header_url' => '',
            'visible' => false,
            'show_votes' => false,
            'entry_starts_at' => $now,
            'entry_ends_at' => $now,
            'voting_starts_at' => $now,
            'voting_ends_at' => $now,
        ]);
        Count::updateOrCreate(['name' => static::CONTEST_COUNT], ['count' => $contest->getKey()]);

        return $contest;
    }

    /** When the season ends: the clients stop showing the backgrounds after it. */
    public static function endsAt(): ?CarbonImmutable
    {
        $configured = $GLOBALS['cfg']['osu']['seasonal']['ends_at'] ?? null;
        if (present($configured)) {
            return CarbonImmutable::parse($configured);
        }
        $timestamp = Count::find(static::ENDS_AT_COUNT)?->count;

        return $timestamp === null ? null : CarbonImmutable::createFromTimestamp($timestamp);
    }

    public static function setEndsAt(CarbonImmutable $endsAt): void
    {
        Count::updateOrCreate(['name' => static::ENDS_AT_COUNT], ['count' => $endsAt->getTimestamp()]);
    }

    /** Writes the "_opt.jpg" copy the clients load (JPEG, at most MAX_SIZE on its longest side). */
    public static function makeClientCopy(UserContestEntry $entry, string $srcPath): void
    {
        $image = @imagecreatefromstring((string) file_get_contents($srcPath));
        if ($image === false) {
            throw new \InvalidArgumentException('that file isn\'t an image');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, static::MAX_SIZE / max($width, $height));
        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = ob_get_clean();

        \Storage::put(static::clientCopyPath($entry), $jpeg, 'public');
    }

    /** The copy's storage path: the entry's file with "_opt.jpg" for its extension (what seasonalUrl links). */
    public static function clientCopyPath(UserContestEntry $entry): string
    {
        $hash = preg_replace('#(\.[^/.]+)$#', '', $entry->hash);

        return "user-contest-entries/{$entry->getKey()}/{$hash}_opt.jpg";
    }
}
