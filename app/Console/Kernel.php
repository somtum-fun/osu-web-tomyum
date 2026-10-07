<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('archive-forum-topics')
            ->cron('*/30 * * * *')
            ->withoutOverlapping(120)
            ->onOneServer();

        $schedule->command('store:cleanup-stale-orders')
            ->daily()
            ->onOneServer();

        $schedule->command('store:expire-products')
            ->hourly()
            ->onOneServer();

        $schedule->command('builds:update-propagation-history')
            ->everyThirtyMinutes()
            ->onOneServer();

        $schedule->command('forum:topic-cover-cleanup --no-interaction')
            ->daily()
            ->onOneServer();

        $schedule->command('rankings:recalculate-country-stats')
            ->cron('25 0,3,6,9,12,15,18,21 * * *')
            ->onOneServer();

        $schedule->command('rankings:recalculate-team-stats')
            ->cron('25 0,3,6,9,12,15,18,21 * * *')
            ->onOneServer();

        $schedule->command('rankings:recalculate-top-plays')
            ->cron('35 0,3,6,9,12,15,18,21 * * *')
            ->onOneServer();

        // TomYum: tournaments whose registration closed: who no longer qualifies is marked for staff.
        $schedule->command('tomyum:tournaments-recheck')
            ->everyTenMinutes()
            ->withoutOverlapping(30)
            ->onOneServer();

        // Ranks sets qualified here once their qualified period is over. With TomYum deciding ranked
        // status, Beatmapset::setApproved passes the change on to it (the game follows); sets imported
        // qualified from osu! have no queue date here, so they're left to osu!'s own status.
        // TomYum: new email to the ticket addresses becomes support tickets (staff panel → Tickets).
        $schedule->command('tomyum:tickets-fetch')
            ->everyMinute()
            ->withoutOverlapping(10)
            ->onOneServer();

        // TomYum: players online every 5 minutes, for the admin panel's chart.
        $schedule->command('tomyum:online-sample')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->onOneServer();

        // TomYum: the sign-up rules' hosting network and Tor lists, and the sign-up log's 90 days.
        $schedule->command('tomyum:registration-lists-update')
            ->cron('40 4 * * *')
            ->onOneServer();

        $schedule->command('tomyum:registrations-prune')
            ->cron('50 4 * * *')
            ->onOneServer();

        $schedule->command('modding:rank')
            ->cron('*/20 * * * *')
            ->withoutOverlapping(120)
            ->onOneServer();

        $schedule->command('oauth:delete-expired-tokens')
            ->cron('14 1 * * *')
            ->onOneServer();

        $schedule->command('notifications:news-published')
            ->everyThirtyMinutes()
            ->withoutOverlapping(120)
            ->onOneServer();

        $schedule->command('notifications:send-mail')
            ->hourly()
            ->withoutOverlapping(120)
            ->onOneServer();

        $schedule->command('user-notifications:cleanup')
            ->everyThirtyMinutes()
            ->withoutOverlapping(120)
            ->onOneServer();

        $schedule->command('notifications:cleanup')
            ->cron('15,45 * * * *')
            ->withoutOverlapping(120)
            ->onOneServer();

        $schedule->command('chat:expire-ack')
            ->everyFiveMinutes()
            ->withoutOverlapping(30)
            ->onOneServer();

        $schedule->command('daily-challenge:create-next')
            ->cron('5 0 * * *')
            ->onOneServer();

        $schedule->command('daily-challenge:user-stats-calculate')
            ->cron('10 0 * * *')
            ->onOneServer();

        $schedule->command('user-count-by-ruleset:recalculate')
            ->cron('20 0 * * *')
            ->withoutOverlapping(120)
            ->onOneServer();

        $schedule->command('ranked-play:decay-ratings')
            ->daily()
            ->onOneServer();
    }
}
