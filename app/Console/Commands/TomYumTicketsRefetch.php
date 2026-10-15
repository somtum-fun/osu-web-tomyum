<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumTickets;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * TomYum: reads the ticket mailbox's recent emails again and fills in the ticket messages saved
 * before tickets kept whole emails: full text, quote, HTML, attachments, Message-ID.
 */
class TomYumTicketsRefetch extends Command
{
    protected $signature = 'tomyum:tickets-refetch {--days=30 : How far back to read the mailbox} {--dry-run : Only list what would change}';

    protected $description = 'Fills in older ticket messages (whole text, pictures and files) from the mailbox.';

    public function handle()
    {
        $count = TomYumTickets::refetch(
            Carbon::now()->subDays((int) $this->option('days')),
            (bool) $this->option('dry-run'),
            fn (string $line) => $this->line($line),
        );
        $this->info(($this->option('dry-run') ? 'Would fill in ' : 'Filled in ')."{$count} message(s).");
    }
}
