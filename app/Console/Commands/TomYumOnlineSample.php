<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumAdmin;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;

/**
 * TomYum: notes how many players are online now (the game server's /online, by client; bots left
 * out), for the admin panel's players-online chart, and forgets samples older than 30 days.
 */
class TomYumOnlineSample extends Command
{
    protected $signature = 'tomyum:online-sample';

    protected $description = 'Records how many players are online, for the admin panel chart.';

    public function handle()
    {
        $response = (new TomYumAdmin())->get('/online');
        if (!$response->successful()) {
            $this->warn('The game server didn\'t answer.');

            return;
        }

        $online = collect($response->json() ?? []);
        $bots = DB::table('phpbb_users')
            ->whereIn('user_id', $online->pluck('userId')->filter()->all())
            ->where('group_id', app('groups')->byIdentifier('bot')->getKey())
            ->pluck('user_id')
            ->push($GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id'])
            ->all();
        $counts = $online->reject(fn ($entry) => in_array($entry['userId'] ?? null, $bots, false))->countBy('client');

        // On the 5-minute mark, so two runs in the same slot are one sample.
        $at = Carbon::now()->startOfMinute();
        $at->minute -= $at->minute % 5;
        DB::table('tomyum_online_samples')->updateOrInsert(['sampled_at' => $at], [
            'stable' => (int) ($counts['stable'] ?? 0),
            'lazer' => (int) ($counts['lazer'] ?? 0),
            'website' => (int) ($counts['website'] ?? 0),
        ]);
        DB::table('tomyum_online_samples')->where('sampled_at', '<', Carbon::now()->subDays(30))->delete();
    }
}
