<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumRegistrationGuard;
use Illuminate\Console\Command;

/** TomYum: forgets sign-ups (made and refused) older than 90 days (tomyum_registrations). */
class TomYumRegistrationsPrune extends Command
{
    protected $signature = 'tomyum:registrations-prune';

    protected $description = 'Removes sign-up log rows older than 90 days.';

    public function handle()
    {
        $removed = TomYumRegistrationGuard::prune();
        if ($removed > 0) {
            $this->info("{$removed} old sign-up row(s) removed.");
        }
    }
}
