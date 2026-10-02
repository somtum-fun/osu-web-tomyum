<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Libraries;

use Carbon\CarbonImmutable;
use DB;
use DOMDocument;
use DOMElement;
use DOMNode;
use Http;

/**
 * TomYum: osu!'s featured artists, read from osu.ppy.sh's own pages (there's no API for them): the
 * track listing (JSON, 50 a page, with each track's album and artist) and each artist's page (its
 * description, images and links are only in the HTML). Images, previews and .osz files stay on
 * osu!'s servers (assets.ppy.sh); only the details are copied, with osu!'s ids.
 */
class TomYumArtistsImport
{
    public const BASE = 'https://osu.ppy.sh';

    public const USER_AGENT = 'TomYum (osu! private server) featured artist import';

    /** The link buttons on an artist's page that are columns of artists. */
    private const LINK_COLUMNS = ['website', 'twitter', 'facebook', 'soundcloud', 'bandcamp', 'patreon', 'youtube', 'spotify'];

    private array $artists = [];
    private array $albums = [];
    private array $tracks = [];

    /** @param \Closure(string): void $log */
    public function __construct(private bool $update, private int $delayMs, private \Closure $log)
    {
    }

    /**
     * Only osu! profile links, for osu!'s artists here without one (artists imported before they were
     * kept): their pages are read again, and nothing else changes. The number filled in.
     */
    public function fillProfiles(): int
    {
        $ids = DB::table('artists')
            ->whereNull('osu_profile_url')
            ->where('id', '<', \App\Http\Controllers\Panel\ArtistsController::OWN_ID_FLOOR['artists'])
            ->orderBy('id')
            ->pluck('id');
        $filled = 0;
        foreach ($ids as $index => $id) {
            $this->artists[$id] = ['id' => $id];
            $this->readArtistPage($id);
            $url = $this->artists[$id]['osu_profile_url'] ?? null;
            if ($url !== null) {
                $filled += DB::table('artists')->where('id', $id)->whereNull('osu_profile_url')->update(['osu_profile_url' => $url]);
            }
            if (($index + 1) % 50 === 0) {
                ($this->log)('Profiles: '.($index + 1).' of '.count($ids)." read, {$filled} filled in");
            }
        }

        return $filled;
    }

    /**
     * Reads the track listing, then goes artist by artist: each one (its page read when it's new, or
     * with --update) is saved with its albums and tracks straight away, so a run that stops keeps
     * what it got, and the next one carries on. $limit: only that many artists (a trial run).
     *
     * @return array{artists: int, albums: int, tracks: int}
     */
    public function run(?int $limit = null): array
    {
        $this->readTracks();
        $this->readArtistList();

        $artistIds = array_keys($this->artists);
        sort($artistIds);
        if ($limit !== null) {
            $artistIds = array_slice($artistIds, 0, $limit);
        }
        $existing = DB::table('artists')->pluck('id')->flip()->all();
        $counts = ['artists' => 0, 'albums' => 0, 'tracks' => 0];
        $total = count($artistIds);
        foreach ($artistIds as $index => $id) {
            $new = !isset($existing[$id]);
            if ($new || $this->update) {
                $this->readArtistPage($id);
            }
            $albums = array_filter($this->albums, fn ($album) => $album['artist_id'] === $id);
            $tracks = array_filter($this->tracks, fn ($track) => $track['artist_id'] === $id);

            [$savedArtist, $savedAlbums, $savedTracks] = DB::transaction(fn () => [
                $new || $this->update ? $this->save('artists', [$this->artists[$id]]) : 0,
                $this->save('artist_albums', $albums),
                $this->save('artist_tracks', $tracks),
            ]);
            $counts['artists'] += $savedArtist;
            $counts['albums'] += $savedAlbums;
            $counts['tracks'] += $savedTracks;

            $name = $this->artists[$id]['name'] ?? "#{$id}";
            $position = ($index + 1).'/'.$total;
            if ($savedArtist > 0) {
                ($this->log)("[{$position}] ".($new ? 'Imported' : 'Updated')." {$name}: ".count($albums).' albums, '.count($tracks).' tracks');
            } elseif ($savedAlbums + $savedTracks > 0) {
                ($this->log)("[{$position}] {$name}: {$savedAlbums} new albums, {$savedTracks} new tracks");
            }
        }

        return $counts;
    }

    /** Every track (and through them their albums and artists), newest first, 50 a page. */
    private function readTracks(): void
    {
        $cursor = null;
        $page = 0;
        do {
            $json = $this->get('/beatmaps/artists/tracks', array_filter(['cursor_string' => $cursor]), json: true);
            foreach ($json['artist_tracks'] ?? [] as $track) {
                $this->takeTrack($track);
            }
            $cursor = $json['cursor_string'] ?? null;
            if (++$page % 20 === 0) {
                ($this->log)('Tracks: '.count($this->tracks));
            }
        } while ($cursor !== null && count($json['artist_tracks'] ?? []) > 0);
        ($this->log)('Tracks: '.count($this->tracks).', albums: '.count($this->albums));
    }

    /** Artists without tracks in the listing still have a page; the index links them all. */
    private function readArtistList(): void
    {
        $html = $this->get('/beatmaps/artists');
        preg_match_all('#/beatmaps/artists/(\d+)["\']#', $html, $matches);
        foreach (array_unique($matches[1]) as $id) {
            $this->artists[(int) $id] ??= ['id' => (int) $id];
        }
        ($this->log)('Artists: '.count($this->artists));
    }

    private function takeTrack(array $track): void
    {
        $at = static::time($track['updated_at'] ?? null);
        $artistId = (int) $track['artist_id'];
        $this->artists[$artistId] ??= ['id' => $artistId];
        if (isset($track['artist']['name'])) {
            $this->artists[$artistId]['name'] ??= $track['artist']['name'];
        }

        if (($album = $track['album'] ?? null) !== null) {
            $previous = $this->albums[$album['id']]['created_at'] ?? null;
            $this->albums[$album['id']] = [
                'id' => $album['id'],
                'artist_id' => $artistId,
                'title' => $album['title'],
                'title_romanized' => $album['title_romanized'] ?? null,
                'genre' => $album['genre'] ?? '',
                'cover_url' => $album['cover_url'] ?? null,
                'visible' => true,
                // "new" on osu! is its age: its oldest track's.
                'created_at' => $previous === null || $at < $previous ? $at : $previous,
                'updated_at' => $at,
            ];
        }

        $this->tracks[$track['id']] = [
            'id' => $track['id'],
            'artist_id' => $artistId,
            'album_id' => $track['album_id'] ?? null,
            'title' => $track['title'],
            'version' => $track['version'] ?? null,
            'genre' => $track['genre'] ?? '',
            'bpm' => (float) ($track['bpm'] ?? 0),
            'length' => static::seconds($track['length'] ?? 0),
            'exclusive' => (bool) ($track['exclusive'] ?? false),
            'cover_url' => $track['cover_url'] ?? null,
            'preview' => $track['preview'] ?? '',
            'osz' => $track['osz'] ?? '',
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    private function readArtistPage(int $id): void
    {
        try {
            $html = $this->get("/beatmaps/artists/{$id}");
        } catch (\Throwable $e) {
            ($this->log)("Artist {$id}: {$e->getMessage()}");

            return;
        }

        $artist = ['id' => $id] + $this->artists[$id];
        if (preg_match('#<script id="json-artist-\d+" type="application/json">\s*(.*?)\s*</script>#s', $html, $m)) {
            $artist['name'] = json_decode($m[1], true)['name'] ?? $artist['name'] ?? "Artist {$id}";
        }
        if (preg_match("#--header-bg: url\\('([^']+)'\\)#", $html, $m)) {
            $artist['header_url'] = $m[1];
        }
        if (preg_match("#--artist-portrait: url\\('([^']+)'\\)#", $html, $m)) {
            $artist['cover_url'] = $m[1];
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        $description = $xpath->query('//div[contains(@class, "artist__description")]//div[contains(@class, "osu-md")]')->item(0);
        $artist['description'] = $description instanceof DOMElement ? trim(static::markdown($description)) : '';
        $video = $xpath->query('//video[contains(@class, "artist__video")]')->item(0);
        $artist['video_url'] = $video instanceof DOMElement ? presence($video->getAttribute('src')) : null;

        foreach ($xpath->query('//a[contains(@class, "artist-link-button--")]') as $link) {
            $href = $link->getAttribute('href');
            if (preg_match('/artist-link-button--([a-z]+)/', $link->getAttribute('class'), $m) && in_array($m[1], static::LINK_COLUMNS, true)) {
                $artist[$m[1]] = $href;
            } elseif (preg_match('#^https://osu\.ppy\.sh/users/\d+$#', $href)) {
                // Their osu! profile (osu!'s user ids are other people here, so it stays a link to osu!).
                $artist['osu_profile_url'] = $href;
            }
        }

        $this->artists[$id] = $artist;
    }

    /** Rows in by id: new ones only, or (--update) osu!'s details over the ones here. */
    private function save(string $table, array $rows): int
    {
        $rows = array_values(array_filter($rows, fn ($row) => isset($row['name']) || $table !== 'artists'));
        if ($table === 'artists') {
            $rows = array_map(fn ($row) => $row + [
                'description' => '',
                'visible' => true,
                'use_for_linking' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], $rows);
            // Every row has the same columns, for the inserts in bulk.
            $columns = ['id', 'name', 'description', 'visible', 'use_for_linking', 'header_url', 'cover_url', 'video_url', 'osu_profile_url', ...static::LINK_COLUMNS, 'created_at', 'updated_at'];
            $rows = array_map(fn ($row) => array_merge(array_fill_keys($columns, null), array_intersect_key($row, array_flip($columns))), $rows);
        }

        $count = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            if ($this->update) {
                $columns = array_diff(array_keys($chunk[0]), ['id', 'created_at']);
                $count += DB::table($table)->upsert($chunk, ['id'], $columns) > 0 ? count($chunk) : 0;
            } else {
                $count += DB::table($table)->insertOrIgnore($chunk);
            }
        }

        return $count;
    }

    private function get(string $path, array $query = [], bool $json = false): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            usleep($this->delayMs * 1000);
            $response = Http::withHeaders(['User-Agent' => static::USER_AGENT, 'Accept' => $json ? 'application/json' : 'text/html'])
                ->timeout(30)
                ->get(static::BASE.$path, $query);

            // Asked to slow down: wait as long as osu! says (a minute when it doesn't), a few times.
            if ($response->status() === 429 && $attempt < 5) {
                $wait = (int) ($response->header('Retry-After') ?: 60);
                ($this->log)("osu! asked to slow down; waiting {$wait}s.");
                sleep(min(max($wait, 5), 300));
                continue;
            }
            $response->throw();

            return $json ? $response->json() : $response->body();
        }
    }

    /** "3:05" (or seconds) as seconds. */
    public static function seconds(mixed $length): int
    {
        if (is_string($length) && preg_match('/^(\d+):(\d{1,2})$/', $length, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }

        return (int) $length;
    }

    private static function time(?string $value): CarbonImmutable
    {
        try {
            return $value === null ? CarbonImmutable::now() : CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return CarbonImmutable::now();
        }
    }

    /** osu!'s rendered description back to the markdown it's stored as (paragraphs, emphasis, links, lists). */
    public static function markdown(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                $out .= preg_replace('/\s+/', ' ', $child->textContent);
                continue;
            }
            $inner = static::markdown($child);
            $out .= match (strtolower($child->tagName)) {
                'p', 'div' => trim($inner)."\n\n",
                'br' => "  \n",
                'strong', 'b' => '**'.trim($inner).'**',
                'em', 'i' => '*'.trim($inner).'*',
                'a' => '['.trim($inner).']('.$child->getAttribute('href').')',
                'h1', 'h2', 'h3', 'h4' => str_repeat('#', (int) substr($child->tagName, 1)).' '.trim($inner)."\n\n",
                'ul', 'ol' => rtrim($inner)."\n\n",
                'li' => '- '.trim($inner)."\n",
                'img' => '!['.$child->getAttribute('alt').']('.$child->getAttribute('src').')',
                'code' => '`'.$inner.'`',
                default => $inner,
            };
        }

        // The HTML's spacing between blocks isn't text: no line starts with it.
        return preg_replace(["/\n[ \t]+/", "/\n{3,}/"], ["\n", "\n\n"], $out);
    }
}
