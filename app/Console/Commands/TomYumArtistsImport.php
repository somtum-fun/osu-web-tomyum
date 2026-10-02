<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumArtistsImport as Import;
use Illuminate\Console\Command;

/**
 * TomYum: copies osu!'s featured artists (artists, albums, tracks) into this website, with osu!'s
 * ids, so the Featured Artists pages work and sets' featured artist links point at them. Only new
 * ones by default, so changes made here stay; --update takes osu!'s again for those already here.
 */
class TomYumArtistsImport extends Command
{
    protected $signature = 'tomyum:artists-import {--update : Take osu!\'s details again for artists, albums and tracks already here} {--delay=1500 : Milliseconds between requests to osu! (it refuses many more)} {--limit= : Only this many artists (a trial run)}';

    protected $description = 'Copies osu!\'s featured artists, albums and tracks in (new ones; --update for all).';

    public function handle()
    {
        $import = new Import(
            update: (bool) $this->option('update'),
            delayMs: max(500, (int) $this->option('delay')),
            log: fn (string $line) => $this->line($line),
        );

        $limit = $this->option('limit');
        $counts = $import->run($limit === null ? null : (int) $limit);
        $this->info("Artists: {$counts['artists']}, albums: {$counts['albums']}, tracks: {$counts['tracks']} (added or updated).");

        // The tracks search (and the quick search's artist tracks) read the search index.
        $this->call('es:index-documents', ['--types' => 'artist_tracks', '--no-interaction' => true, '--cleanup' => true]);
    }
}
