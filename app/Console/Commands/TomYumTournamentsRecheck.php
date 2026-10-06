<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * TomYum: when a tournament's registration closes, checks everyone registered against its
 * requirements again; who no longer qualifies is marked for staff (Panel\TournamentsController).
 */
class TomYumTournamentsRecheck extends Command
{
    protected $signature = 'tomyum:tournaments-recheck';

    protected $description = 'Checks the registrations of tournaments whose registration just closed.';

    public function handle()
    {
        $tournaments = Tournament::where('is_banner_only', false)
            ->where('signup_close', '<=', Carbon::now())
            ->where(fn ($q) => $q->whereNull('rechecked_at')->orWhereColumn('rechecked_at', '<', 'signup_close'))
            ->get();

        foreach ($tournaments as $tournament) {
            $flagged = $tournament->recheckRegistrations();
            $this->info("{$tournament->name}: {$flagged} no longer qualify.");
        }
    }
}
