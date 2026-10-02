<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace Tests\Controllers\Panel;

use App\Http\Controllers\Panel\ArtistsController;
use App\Models\Artist;
use App\Models\ArtistAlbum;
use App\Models\ArtistTrack;
use App\Models\Beatmapset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ArtistsControllerTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->withGroup('admin')->create();
    }

    public function testOnlyAdminsGetIn(): void
    {
        $this->actAsUser(User::factory()->create(), true)
            ->get(route('panel.admin.artists.index'))
            ->assertStatus(403);
        $this->actAsUser($this->admin, true)
            ->get(route('panel.admin.artists.index'))
            ->assertSuccessful();
    }

    public function testANewArtistIsOurOwn_AndHiddenUntilShown(): void
    {
        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.artists.store'), ['name' => 'New Artist'])
            ->assertRedirect();

        $artist = Artist::where('name', 'New Artist')->firstOrFail();
        $this->assertGreaterThanOrEqual(ArtistsController::OWN_ID_FLOOR['artists'], $artist->getKey());
        $this->assertFalse((bool) $artist->visible);

        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.artists.update', ['artist' => $artist->getKey()]), [
                'name' => 'Renamed',
                'visible' => '1',
                'description' => "Some **words**.\n\nMore.",
                'website' => 'https://artist.example',
                'twitter' => '',
                'cover_url' => 'https://assets.ppy.sh/artists/1/the tea party house/cover.jpg',
            ])
            ->assertRedirect()
            ->assertSessionHas('panel_status.type', 'success');

        $artist->refresh();
        $this->assertSame('Renamed', $artist->name);
        $this->assertTrue((bool) $artist->visible);
        $this->assertSame('https://artist.example', $artist->website);
        $this->assertNull($artist->twitter);
        $this->assertSame('https://assets.ppy.sh/artists/1/the tea party house/cover.jpg', $artist->cover_url);

        $this->actAsUser($this->admin, true)
            ->get(route('panel.admin.artists.show', ['artist' => $artist->getKey()]))
            ->assertSuccessful()
            ->assertSee('Renamed');
    }

    public function testBadLinksAreRefused(): void
    {
        $artist = $this->artist();

        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.artists.update', ['artist' => $artist->getKey()]), [
                'name' => 'Still here',
                'website' => 'javascript:alert(1)',
            ])
            ->assertSessionHas('panel_status.type', 'error');

        $this->assertNotSame('Still here', $artist->fresh()->name);
    }

    public function testAlbumsAndTracks(): void
    {
        $artist = $this->artist();

        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.artists.albums.store', ['artist' => $artist->getKey()]), [
                'title' => 'First EP',
                'genre' => 'Electronic',
                'visible' => '1',
            ])
            ->assertSessionHas('panel_status.type', 'success');
        $album = ArtistAlbum::where('title', 'First EP')->firstOrFail();
        $this->assertGreaterThanOrEqual(ArtistsController::OWN_ID_FLOOR['artist_albums'], $album->getKey());

        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.artists.tracks.store', ['artist' => $artist->getKey()]), [
                'title' => 'Opening',
                'album_id' => (string) $album->getKey(),
                'genre' => 'Electronic',
                'bpm' => '174',
                'length' => '3:05',
                'exclusive' => '1',
                'preview_file' => UploadedFile::fake()->create('preview.mp3', 200, 'audio/mpeg'),
                'osz' => 'https://files.example/opening.osz',
            ])
            ->assertSessionHas('panel_status.type', 'success');
        $track = ArtistTrack::where('title', 'Opening')->firstOrFail();
        $this->assertGreaterThanOrEqual(ArtistsController::OWN_ID_FLOOR['artist_tracks'], $track->getKey());
        $this->assertSame($album->getKey(), $track->album_id);
        $this->assertSame(185, $track->length);
        $this->assertTrue((bool) $track->exclusive);
        $this->assertStringContainsString("/uploads/artists/{$artist->getKey()}/tracks/{$track->getKey()}/preview.mp3", $track->preview);
        $this->assertFileExists(public_path("uploads/artists/{$artist->getKey()}/tracks/{$track->getKey()}/preview.mp3"));

        // A track without a preview, or of another artist's album, isn't taken.
        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.artists.tracks.store', ['artist' => $artist->getKey()]), [
                'title' => 'No preview', 'genre' => 'x', 'bpm' => '120', 'length' => '1:00', 'osz' => 'https://files.example/a.osz',
            ])
            ->assertSessionHas('panel_status.type', 'error');
        $other = $this->album($this->artist());
        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.artists.tracks.update', ['artist' => $artist->getKey(), 'track' => $track->getKey()]), [
                'title' => 'Opening', 'album_id' => (string) $other->getKey(), 'genre' => 'x', 'bpm' => '120', 'length' => '1:00',
                'preview' => $track->preview, 'osz' => $track->osz,
            ])
            ->assertSessionHas('panel_status.type', 'error');

        // Removing the album keeps its tracks, as singles.
        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.artists.albums.destroy', ['artist' => $artist->getKey(), 'album' => $album->getKey()]))
            ->assertSessionHas('panel_status.type', 'success');
        $this->assertNull($track->fresh()->album_id);

        // Removing the track unlinks beatmap sets of it.
        $set = Beatmapset::factory()->create(['track_id' => $track->getKey()]);
        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.artists.tracks.destroy', ['artist' => $artist->getKey(), 'track' => $track->getKey()]))
            ->assertSessionHas('panel_status.type', 'success');
        $this->assertNull(ArtistTrack::find($track->getKey()));
        $this->assertNull($set->fresh()->track_id);

        @unlink(public_path("uploads/artists/{$artist->getKey()}/tracks/{$track->getKey()}/preview.mp3"));
    }

    public function testRemovingAnArtist_TakesTheirAlbumsAndTracks(): void
    {
        $artist = $this->artist();
        $album = $this->album($artist);
        $track = ArtistTrack::factory()->create(['artist_id' => $artist->getKey(), 'album_id' => $album->getKey()]);

        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.artists.destroy', ['artist' => $artist->getKey()]))
            ->assertRedirect(route('panel.admin.artists.index'));

        $this->assertNull(Artist::find($artist->getKey()));
        $this->assertNull(ArtistAlbum::find($album->getKey()));
        $this->assertNull(ArtistTrack::find($track->getKey()));
    }

    public function testLengths(): void
    {
        $this->assertSame(185, ArtistsController::seconds('3:05'));
        $this->assertSame(65, ArtistsController::seconds('65'));
        $this->assertNull(ArtistsController::seconds('3:75'));
        $this->assertNull(ArtistsController::seconds('soon'));
    }

    private function album(Artist $artist): ArtistAlbum
    {
        $album = new ArtistAlbum();
        $album->forceFill(['artist_id' => $artist->getKey(), 'title' => 'Album', 'genre' => 'x', 'visible' => true])->save();

        return $album;
    }

    private function artist(): Artist
    {
        return Artist::factory()->create(['visible' => true]);
    }
}
