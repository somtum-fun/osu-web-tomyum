<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Models\Artist;
use App\Models\ArtistAlbum;
use App\Models\ArtistTrack;
use Auth;
use DB;
use Illuminate\Http\UploadedFile;
use Request;

/**
 * Admin panel: featured artists (osu!'s, copied in with tomyum:artists-import, and this server's
 * own): their details, links and images, their albums and their tracks. Images, previews and .osz
 * files are either addresses (osu!'s stay on assets.ppy.sh) or uploaded here (uploads/artists/…).
 * New ones get ids far above osu!'s, so a later import never runs into them.
 */
class ArtistsController extends Controller
{
    /** This server's own ones start here (osu! has a few hundred artists, a few thousand albums, ~12k tracks). */
    public const OWN_ID_FLOOR = ['artists' => 100_000, 'artist_albums' => 1_000_000, 'artist_tracks' => 10_000_000];

    public const LINKS = [
        'website' => ['Website', 'fas fa-link'],
        'twitter' => ['Twitter / X', 'fab fa-twitter'],
        'youtube' => ['YouTube', 'fab fa-youtube'],
        'soundcloud' => ['SoundCloud', 'fab fa-soundcloud'],
        'bandcamp' => ['Bandcamp', 'fab fa-bandcamp'],
        'spotify' => ['Spotify', 'fab fa-spotify'],
        'patreon' => ['Patreon', 'fab fa-patreon'],
        'facebook' => ['Facebook', 'fab fa-facebook'],
    ];

    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
    private const MAX_AUDIO_BYTES = 15 * 1024 * 1024;
    private const MAX_OSZ_BYTES = 100 * 1024 * 1024;

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
        $query = trim((string) Request::input('q'));
        $artists = Artist::withCount(['tracks', 'albums'])
            ->when($query !== '', fn ($q) => ctype_digit($query)
                ? $q->where('id', (int) $query)
                : $q->where('name', 'like', '%'.addcslashes($query, '%_\\').'%'))
            ->orderBy('name')
            ->paginate(60)
            ->withQueryString();

        return ext_view('panel.admin.artists.index', [
            'artists' => $artists,
            'query' => $query,
            'totals' => [
                'artists' => Artist::count(),
                'own' => Artist::where('id', '>=', static::OWN_ID_FLOOR['artists'])->count(),
                'hidden' => Artist::where('visible', false)->count(),
                'tracks' => ArtistTrack::count(),
            ],
        ]);
    }

    public function store()
    {
        $name = trim((string) Request::input('name'));
        if ($name === '') {
            return $this->backToIndex('error', 'Give the artist a name.');
        }

        $artist = new Artist();
        $artist->forceFill([
            'id' => static::nextId('artists'),
            'name' => mb_substr($name, 0, 255),
            'description' => '',
            // Hidden until it's ready: the pages and the track search leave it out till then.
            'visible' => false,
            'use_for_linking' => true,
        ])->save();
        $this->staffLog('artist.add', $artist->getKey(), "Added the featured artist {$artist->name}");

        return $this->back($artist, 'success', 'Added (hidden until you show it). Fill in the rest below.');
    }

    public function show(string $artist)
    {
        $artist = Artist::findOrFail((int) $artist);
        $albums = $artist->albums()->orderByDesc('id')->get();
        $tracks = $artist->tracks()->orderBy('display_order')->orderByDesc('exclusive')->orderBy('id')->get()->groupBy('album_id');

        return ext_view('panel.admin.artists.show', [
            'artist' => $artist,
            'albums' => $albums,
            'tracks' => $tracks,
            'links' => static::LINKS,
            'beatmapsets' => DB::table('osu_beatmapsets')->whereIn('track_id', $artist->tracks()->select('id'))->count(),
        ]);
    }

    public function update(string $artist)
    {
        $artist = Artist::findOrFail((int) $artist);
        $name = trim((string) Request::input('name'));
        if ($name === '') {
            return $this->back($artist, 'error', 'The artist needs a name.');
        }

        $fields = [
            'name' => mb_substr($name, 0, 255),
            'description' => (string) Request::input('description'),
            'visible' => Request::boolean('visible'),
        ];
        foreach ([...array_keys(static::LINKS), 'video_url'] as $column) {
            [$fields[$column], $problem] = static::url(Request::input($column));
            if ($problem !== null) {
                return $this->back($artist, 'error', (static::LINKS[$column][0] ?? 'Video').": {$problem}");
            }
        }
        foreach (['header_url' => 'header', 'cover_url' => 'cover'] as $column => $name) {
            [$fields[$column], $problem] = $this->fileOrUrl("{$name}_file", $column, "artists/{$artist->getKey()}/{$name}", 'image', retina: true);
            if ($problem !== null) {
                return $this->back($artist, 'error', ucfirst($name).": {$problem}");
            }
        }

        $visibilityChanged = (bool) $artist->visible !== $fields['visible'];
        $artist->forceFill($fields)->save();
        if ($visibilityChanged) {
            static::reindex($artist->tracks()->get());
        }
        $this->staffLog('artist.edit', $artist->getKey(), "Changed the featured artist {$artist->name}");

        return $this->back($artist, 'success', 'Saved.');
    }

    public function destroy(string $artist)
    {
        $artist = Artist::findOrFail((int) $artist);
        $name = $artist->name;
        DB::transaction(function () use ($artist) {
            $trackIds = $artist->tracks()->pluck('id');
            DB::table('osu_beatmapsets')->whereIn('track_id', $trackIds)->update(['track_id' => null]);
            foreach ($artist->tracks()->get() as $track) {
                $track->esDeleteDocument();
            }
            $artist->tracks()->delete();
            $artist->albums()->delete();
            $artist->delete();
        });
        $this->staffLog('artist.remove', $artist->getKey(), "Removed the featured artist {$name}");

        return $this->backToIndex('success', "Removed {$name}, with their albums and tracks.");
    }

    public function albumStore(string $artist)
    {
        $artist = Artist::findOrFail((int) $artist);
        $album = new ArtistAlbum();
        $album->forceFill(['id' => static::nextId('artist_albums'), 'artist_id' => $artist->getKey(), 'visible' => true]);
        if (($problem = $this->fillAlbum($album)) !== null) {
            return $this->back($artist, 'error', $problem);
        }
        $album->save();
        $this->staffLog('artist.album_add', $artist->getKey(), "Added the album {$album->title} to {$artist->name}");

        return $this->back($artist, 'success', "Added the album {$album->title}.", "album-{$album->getKey()}");
    }

    public function albumUpdate(string $artist, string $album)
    {
        [$artist, $album] = $this->album($artist, $album);
        $wasVisible = (bool) $album->visible;
        if (($problem = $this->fillAlbum($album)) !== null) {
            return $this->back($artist, 'error', $problem, "album-{$album->getKey()}");
        }
        $album->save();
        if ($wasVisible !== (bool) $album->visible) {
            static::reindex($album->tracks()->get());
        }

        return $this->back($artist, 'success', "Saved the album {$album->title}.", "album-{$album->getKey()}");
    }

    public function albumDestroy(string $artist, string $album)
    {
        [$artist, $album] = $this->album($artist, $album);
        // Its tracks stay, as singles.
        DB::transaction(function () use ($album) {
            $album->tracks()->update(['album_id' => null]);
            $album->delete();
        });
        static::reindex($artist->tracks()->whereNull('album_id')->get());
        $this->staffLog('artist.album_remove', $artist->getKey(), "Removed the album {$album->title} from {$artist->name}");

        return $this->back($artist, 'success', "Removed the album {$album->title} (its tracks are singles now).");
    }

    public function trackStore(string $artist)
    {
        $artist = Artist::findOrFail((int) $artist);
        $track = new ArtistTrack();
        $track->forceFill(['id' => static::nextId('artist_tracks'), 'artist_id' => $artist->getKey()]);
        if (($problem = $this->fillTrack($artist, $track)) !== null) {
            return $this->back($artist, 'error', $problem);
        }
        $track->save();
        static::reindex([$track]);
        $this->staffLog('artist.track_add', $artist->getKey(), "Added the track {$track->title} to {$artist->name}");

        return $this->back($artist, 'success', "Added the track {$track->title}.", "track-{$track->getKey()}");
    }

    public function trackUpdate(string $artist, string $track)
    {
        [$artist, $track] = $this->track($artist, $track);
        if (($problem = $this->fillTrack($artist, $track)) !== null) {
            return $this->back($artist, 'error', $problem, "track-{$track->getKey()}");
        }
        $track->save();
        static::reindex([$track]);

        return $this->back($artist, 'success', "Saved the track {$track->title}.", "track-{$track->getKey()}");
    }

    public function trackDestroy(string $artist, string $track)
    {
        [$artist, $track] = $this->track($artist, $track);
        DB::transaction(function () use ($track) {
            DB::table('osu_beatmapsets')->where('track_id', $track->getKey())->update(['track_id' => null]);
            $track->delete();
        });
        $track->esDeleteDocument();
        $this->staffLog('artist.track_remove', $artist->getKey(), "Removed the track {$track->title} from {$artist->name}");

        return $this->back($artist, 'success', "Removed the track {$track->title}.");
    }

    /** The album form into the album; the problem, if any. */
    private function fillAlbum(ArtistAlbum $album): ?string
    {
        $title = trim((string) Request::input('title'));
        if ($title === '') {
            return 'Give the album a title.';
        }
        [$cover, $problem] = $this->fileOrUrl('cover_file', 'cover_url', "artists/{$album->artist_id}/albums/{$album->getKey()}/cover", 'image');
        if ($problem !== null) {
            return "Cover: {$problem}";
        }
        $album->forceFill([
            'title' => mb_substr($title, 0, 255),
            'title_romanized' => presence(mb_substr(trim((string) Request::input('title_romanized')), 0, 255)),
            'genre' => mb_substr(trim((string) Request::input('genre')), 0, 255),
            'cover_url' => $cover,
            'visible' => Request::boolean('visible'),
        ]);

        return null;
    }

    /** The track form into the track; the problem, if any. */
    private function fillTrack(Artist $artist, ArtistTrack $track): ?string
    {
        $title = trim((string) Request::input('title'));
        if ($title === '') {
            return 'Give the track a title.';
        }
        $length = static::seconds((string) Request::input('length'));
        if ($length === null) {
            return 'The length should be like 3:05.';
        }
        $bpm = (float) Request::input('bpm');
        if ($bpm <= 0 || $bpm > 1000) {
            return 'The BPM should be a number above 0.';
        }
        $albumId = get_int(Request::input('album_id'));
        if ($albumId !== null && !$artist->albums()->whereKey($albumId)->exists()) {
            return 'That album isn\'t this artist\'s.';
        }

        $dir = "artists/{$artist->getKey()}/tracks/{$track->getKey()}";
        [$cover, $problem] = $this->fileOrUrl('cover_file', 'cover_url', "{$dir}/cover", 'image');
        if ($problem !== null) {
            return "Cover: {$problem}";
        }
        [$preview, $problem] = $this->fileOrUrl('preview_file', 'preview', "{$dir}/preview", 'audio');
        if ($problem !== null || $preview === null) {
            return 'Preview: '.($problem ?? 'upload an MP3, or give the address of one.');
        }
        [$osz, $problem] = $this->fileOrUrl('osz_file', 'osz', "{$dir}/{$artist->name} - {$title}", 'osz');
        if ($problem !== null || $osz === null) {
            return 'Download (.osz): '.($problem ?? 'upload the .osz template, or give the address of one.');
        }

        $track->forceFill([
            'album_id' => $albumId,
            'title' => mb_substr($title, 0, 255),
            'title_romanized' => presence(mb_substr(trim((string) Request::input('title_romanized')), 0, 255)),
            'version' => presence(mb_substr(trim((string) Request::input('version')), 0, 255)),
            'genre' => mb_substr(trim((string) Request::input('genre')), 0, 255),
            'bpm' => $bpm,
            'length' => $length,
            'exclusive' => Request::boolean('exclusive'),
            'display_order' => get_int(Request::input('display_order')),
            'cover_url' => $cover,
            'preview' => $preview,
            'osz' => $osz,
        ]);

        return null;
    }

    /**
     * A file field's upload (stored in uploads/{$path}.{ext}), else the address in the URL field
     * (empty: none): [url, null] or [null, why not]. Retina: the image is stored at @2x too, for the
     * artist page's sharp header and portrait.
     */
    private function fileOrUrl(string $fileField, string $urlField, string $path, string $kind, bool $retina = false): array
    {
        $file = Request::file($fileField);
        if ($file instanceof UploadedFile) {
            [$extensions, $max] = match ($kind) {
                'image' => [['jpg', 'jpeg', 'png', 'webp', 'gif'], static::MAX_IMAGE_BYTES],
                'audio' => [['mp3', 'ogg'], static::MAX_AUDIO_BYTES],
                'osz' => [['osz'], static::MAX_OSZ_BYTES],
            };
            $extension = strtolower($file->getClientOriginalExtension());
            if (!$file->isValid() || $file->getSize() > $max) {
                return [null, 'it didn\'t upload (up to '.intdiv($max, 1024 * 1024).' MB).'];
            }
            if (!in_array($extension, $extensions, true)) {
                return [null, 'use a '.implode(', ', $extensions).' file.'];
            }
            if ($kind === 'image' && @getimagesize($file->getRealPath()) === false) {
                return [null, 'that isn\'t an image.'];
            }

            // A name that's safe in a path and an address (the .osz keeps a readable one: it's what players save).
            $path = preg_replace('#[^A-Za-z0-9/_\- .()\[\]]#', '', $path);
            $relative = "uploads/{$path}.{$extension}";
            $target = public_path($relative);
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            copy($file->getRealPath(), $target);
            if ($retina) {
                copy($file->getRealPath(), retinaify($target));
            }

            return [url(implode('/', array_map('rawurlencode', explode('/', $relative)))).'?'.time(), null];
        }

        return static::url(Request::input($urlField));
    }

    /** A web address, or none: [url, null] or [null, why not]. */
    private static function url(mixed $input): array
    {
        $url = trim((string) $input);
        if ($url === '') {
            return [null, null];
        }
        // Spaces are fine: osu!'s own asset addresses have them (…/the tea party house/cover.jpg).
        if (!preg_match('#^https?://[^\s][^\r\n]*$#i', $url) || strlen($url) > 512) {
            return [null, 'that should be a web address (https://…).'];
        }

        return [$url, null];
    }

    /** "3:05" (or a number of seconds) as seconds; null when it's neither. */
    public static function seconds(string $length): ?int
    {
        $length = trim($length);
        if (preg_match('/^(\d+):([0-5]?\d)$/', $length, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }

        return ctype_digit($length) ? (int) $length : null;
    }

    /** The next id of this server's own (above osu!'s). */
    private static function nextId(string $table): int
    {
        return max(static::OWN_ID_FLOOR[$table], (int) DB::table($table)->max('id') + 1);
    }

    /** The track search follows: visible ones in, hidden ones out. */
    private static function reindex(iterable $tracks): void
    {
        foreach ($tracks as $track) {
            try {
                $track->refresh()->esIndexDocument();
            } catch (\Throwable $e) {
                log_error($e);
            }
        }
    }

    /** @return array{Artist, ArtistAlbum} */
    private function album(string $artistId, string $albumId): array
    {
        $artist = Artist::findOrFail((int) $artistId);

        return [$artist, $artist->albums()->findOrFail((int) $albumId)];
    }

    /** @return array{Artist, ArtistTrack} */
    private function track(string $artistId, string $trackId): array
    {
        $artist = Artist::findOrFail((int) $artistId);

        return [$artist, $artist->tracks()->findOrFail((int) $trackId)];
    }

    private function back(Artist $artist, string $type, string $message, ?string $anchor = null)
    {
        return redirect(route('panel.admin.artists.show', ['artist' => $artist->getKey()]).($anchor === null ? '' : "#{$anchor}"))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }

    private function backToIndex(string $type, string $message)
    {
        return redirect(route('panel.admin.artists.index'))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
