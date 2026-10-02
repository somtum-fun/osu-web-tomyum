<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\Elasticsearch\Indexing;
use App\Models\Wiki\Page;
use Illuminate\Console\Command;

/**
 * TomYum: builds any search index that isn't there yet (run by every deploy). A missing one breaks
 * more than its own results: quick search showed nothing at all while artist_tracks and teams were
 * missing. Indexes that are there are left alone.
 */
class TomYumEsEnsure extends Command
{
    protected $signature = 'tomyum:es-ensure';

    protected $description = 'Builds any missing search index (beatmaps, users, posts, artist tracks, teams, wiki).';

    public function handle()
    {
        $missing = [];
        foreach (EsIndexDocuments::ALLOWED_TYPES as $name => $types) {
            if (count(Indexing::getOldIndices($types[0]::esIndexName())) === 0) {
                $missing[] = $name;
            }
        }

        if (count($missing) > 0) {
            $this->info('Building missing search indexes: '.implode(', ', $missing));
            $this->call('es:index-documents', ['--types' => implode(',', $missing), '--no-interaction' => true]);
        }

        // Wiki pages are indexed as they're first viewed; the index only has to exist.
        $wikiMissing = count(Indexing::getOldIndices(Page::esIndexName())) === 0;
        if ($wikiMissing) {
            $this->info('Building the missing wiki index');
            $this->call('es:index-wiki', ['--create-only' => true, '--no-interaction' => true]);
        }

        if (count($missing) === 0 && !$wikiMissing) {
            $this->line('Search indexes: all there.');
        }
    }
}
