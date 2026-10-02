<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace Tests\Libraries;

use App\Libraries\TomYumArtistsImport;
use App\Models\Artist;
use App\Models\ArtistAlbum;
use App\Models\ArtistTrack;
use Http;
use Tests\TestCase;

class TomYumArtistsImportTest extends TestCase
{
    private const ARTIST_PAGE = <<<'HTML'
        <style>:root { --header-bg: url('https://assets.ppy.sh/artists/9001/header.png'); --header-bg-2x: url('https://assets.ppy.sh/artists/9001/header@2x.png');
        --artist-portrait: url('https://assets.ppy.sh/artists/9001/cover.jpg'); }</style>
        <div class="artist__description"><h1>Test Artist</h1>
            <div class='osu-md'><p class="osu-md__paragraph">The <strong>first</strong> of all, with <a href="https://example.com/ep">an EP</a>.</p>
            <p class="osu-md__paragraph">Second line.</p></div>
        </div>
        <div class="artist__links-area">
            <a class='artist-link-button artist-link-button--osu' href='https://osu.ppy.sh/users/102'>osu! Profile</a>
            <a class='artist-link-button artist-link-button--website' href='https://artist.example'>Website</a>
            <a class='artist-link-button artist-link-button--twitter' href='https://twitter.com/artist'>Twitter</a>
        </div>
        <script id="json-artist-9001" type="application/json">{"id":9001,"name":"Test Artist"}</script>
        HTML;

    private static function track(int $id, ?int $albumId, string $updatedAt): array
    {
        return [
            'id' => $id,
            'artist_id' => 9001,
            'album_id' => $albumId,
            'bpm' => 170,
            'cover_url' => 'https://assets.ppy.sh/artists/9001/cover.jpg',
            'exclusive' => true,
            'genre' => 'Drum & Bass',
            'is_new' => false,
            'length' => '3:05',
            'osz' => "https://assets.ppy.sh/artists/9001/{$id}.osz",
            'preview' => "https://assets.ppy.sh/artists/9001/{$id}.mp3",
            'title' => "Track {$id}",
            'updated_at' => $updatedAt,
            'version' => null,
            'album' => $albumId === null ? null : [
                'id' => $albumId,
                'artist_id' => 9001,
                'title' => 'The Album',
                'title_romanized' => 'The Album (romanized)',
                'genre' => 'Drum & Bass',
                'is_new' => false,
                'cover_url' => 'https://assets.ppy.sh/artists/9001/album.jpg',
            ],
            'artist' => ['id' => 9001, 'name' => 'Test Artist'],
        ];
    }

    private function fakeOsu(): void
    {
        Http::fake([
            'osu.ppy.sh/beatmaps/artists/tracks?cursor_string=next' => Http::response(['artist_tracks' => [], 'cursor_string' => null]),
            'osu.ppy.sh/beatmaps/artists/tracks*' => Http::response([
                'artist_tracks' => [
                    static::track(90011, 9002, '2026-09-01T10:00:00+00:00'),
                    static::track(90012, 9002, '2025-01-01T10:00:00+00:00'),
                    static::track(90013, null, '2024-05-01T10:00:00+00:00'),
                ],
                'cursor_string' => 'next',
            ]),
            'osu.ppy.sh/beatmaps/artists/9001' => Http::response(static::ARTIST_PAGE),
            'osu.ppy.sh/beatmaps/artists' => Http::response('<a href="https://osu.ppy.sh/beatmaps/artists/9001">Test Artist</a>'),
        ]);
    }

    public function testArtistsAlbumsAndTracksComeInWithOsusIds(): void
    {
        $this->fakeOsu();
        $lines = [];

        $counts = (new TomYumArtistsImport(false, 0, function (string $line) use (&$lines) {
            $lines[] = $line;
        }))->run();

        $this->assertSame(['artists' => 1, 'albums' => 1, 'tracks' => 3], $counts);
        $this->assertContains('[1/1] Imported Test Artist: 1 albums, 3 tracks', $lines);

        $artist = Artist::findOrFail(9001);
        $this->assertSame('Test Artist', $artist->name);
        $this->assertSame("The **first** of all, with [an EP](https://example.com/ep).\n\nSecond line.", $artist->description);
        $this->assertSame('https://assets.ppy.sh/artists/9001/header.png', $artist->header_url);
        $this->assertSame('https://assets.ppy.sh/artists/9001/cover.jpg', $artist->cover_url);
        $this->assertSame('https://artist.example', $artist->website);
        $this->assertSame('https://twitter.com/artist', $artist->twitter);
        $this->assertNull($artist->user_id); // osu!'s user 102 isn't this server's user 102
        $this->assertSame('https://osu.ppy.sh/users/102', $artist->osu_profile_url);

        $album = ArtistAlbum::findOrFail(9002);
        $this->assertSame('The Album (romanized)', $album->title_romanized);
        $this->assertSame('2025-01-01', $album->created_at->toDateString()); // its oldest track's day

        $track = ArtistTrack::findOrFail(90011);
        $this->assertSame(185, $track->length);
        $this->assertSame(9002, $track->album_id);
        $this->assertNull(ArtistTrack::findOrFail(90013)->album_id);
    }

    public function testChangesMadeHereStay_UnlessUpdating(): void
    {
        $this->fakeOsu();
        $quiet = fn () => null;
        (new TomYumArtistsImport(false, 0, $quiet))->run();
        Artist::where('id', 9001)->update(['name' => 'Renamed here']);
        ArtistTrack::where('id', 90011)->update(['title' => 'Retitled here']);

        $again = (new TomYumArtistsImport(false, 0, $quiet))->run();
        $this->assertSame(['artists' => 0, 'albums' => 0, 'tracks' => 0], $again);
        $this->assertSame('Renamed here', Artist::findOrFail(9001)->name);
        $this->assertSame('Retitled here', ArtistTrack::findOrFail(90011)->title);

        (new TomYumArtistsImport(true, 0, $quiet))->run();
        $this->assertSame('Test Artist', Artist::findOrFail(9001)->name);
        $this->assertSame('Track 90011', ArtistTrack::findOrFail(90011)->title);
    }

    public function testMissingOsuProfilesAreFilledIn_AndNothingElse(): void
    {
        $this->fakeOsu();
        $quiet = fn () => null;
        (new TomYumArtistsImport(false, 0, $quiet))->run();
        Artist::where('id', 9001)->update(['osu_profile_url' => null, 'name' => 'Renamed here']);

        $this->assertSame(1, (new TomYumArtistsImport(false, 0, $quiet))->fillProfiles());

        $artist = Artist::findOrFail(9001);
        $this->assertSame('https://osu.ppy.sh/users/102', $artist->osu_profile_url);
        $this->assertSame('Renamed here', $artist->name);
        $this->assertSame(0, (new TomYumArtistsImport(false, 0, $quiet))->fillProfiles());
    }

    public function testLengths(): void
    {
        $this->assertSame(185, TomYumArtistsImport::seconds('3:05'));
        $this->assertSame(3725, TomYumArtistsImport::seconds('62:05'));
        $this->assertSame(90, TomYumArtistsImport::seconds(90));
    }
}
