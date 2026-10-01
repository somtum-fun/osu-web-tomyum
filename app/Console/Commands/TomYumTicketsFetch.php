<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumTickets;
use Illuminate\Console\Command;

/** TomYum: turns new email to the ticket addresses into support tickets (App\Libraries\TomYumTickets). */
class TomYumTicketsFetch extends Command
{
    protected $signature = 'tomyum:tickets-fetch';

    protected $description = 'Turns new email to the ticket addresses into support tickets.';

    public function handle()
    {
        if (!$GLOBALS['cfg']['tomyum']['tickets']['enabled']) {
            return;
        }

        [$created, $added] = TomYumTickets::fetch();
        if ($created + $added > 0) {
            $this->info("{$created} new ticket(s), {$added} message(s) added.");
        }
    }
}
