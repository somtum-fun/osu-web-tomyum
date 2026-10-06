<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Enums\Ruleset;
use App\Models\Beatmapset;
use Illuminate\Console\Command;

class ModdingRankCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'modding:rank {--no-wait} {--count-only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rank maps in queue.';

    private bool $countOnly = false;
    private bool $noWait = false;

    public static function getStats(Ruleset $ruleset)
    {
        $rankedTodayCount = Beatmapset::ranked()
            ->withoutTrashed()
            ->withModesForRanking($ruleset->value)
            ->where('approved_date', '>=', now()->subDays())
            ->count();

        // TomYum: rank_per_day 0 is no daily cap (the old server ranked every set once its time was up).
        $rankPerDay = $GLOBALS['cfg']['osu']['beatmapset']['rank_per_day'];

        return [
            'availableQuota' => $rankPerDay > 0 ? $rankPerDay - $rankedTodayCount : PHP_INT_MAX,
            'inQueue' => Beatmapset::toBeRanked($ruleset)->count(),
            'rankedToday' => $rankedTodayCount,
        ];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->countOnly = get_bool($this->option('count-only'));
        $this->noWait = get_bool($this->option('no-wait'));

        if ($this->countOnly) {
            $this->info('Number of beatmapsets in queue:');
        } else {
            $this->info('Ranking beatmapsets...');
        }

        $rulesets = Ruleset::cases();

        shuffle($rulesets);

        foreach ($rulesets as $ruleset) {
            $this->waitRandom();

            if ($this->countOnly) {
                $stats = static::getStats($ruleset);
                $this->info($ruleset->name);
                foreach ($stats as $key => $value) {
                    $this->line("{$key}: {$value}");
                }
                $this->newLine();
            } else {
                $this->rankAll($ruleset);
            }
        }

        $this->info('Done');
    }

    private function rankAll(Ruleset $ruleset)
    {
        $this->info("Ranking beatmapsets with at least mode: {$ruleset->name}");
        $stats = static::getStats($ruleset);

        $quota = $stats['availableQuota'] === PHP_INT_MAX ? 'any number' : $stats['availableQuota'];
        $this->info("{$stats['rankedToday']} beatmapsets ranked last 24 hours. Can rank {$quota} more");

        if ($stats['availableQuota'] <= 0) {
            return;
        }

        // TomYum: rank_per_run 0 is no limit per run either.
        $rankPerRun = $GLOBALS['cfg']['osu']['beatmapset']['rank_per_run'];
        $toRankLimit = min($rankPerRun > 0 ? $rankPerRun : PHP_INT_MAX, $stats['availableQuota']);

        $toBeRanked = Beatmapset::tobeRanked($ruleset)
            ->orderBy('queued_at', 'ASC');
        if ($toRankLimit < PHP_INT_MAX) {
            $toBeRanked->limit($toRankLimit);
        }
        $toBeRanked = $toBeRanked->get();

        $this->info("{$stats['inQueue']} beatmapset(s) in ranking queue");
        $this->info("Ranking {$toBeRanked->count()} beatmapset(s)");

        foreach ($toBeRanked as $beatmapset) {
            $this->waitRandom();
            $this->info("Ranking beatmapset: {$beatmapset->getKey()}");
            $beatmapset->rank();
        }
    }

    private function waitRandom()
    {
        if ($this->noWait || $this->countOnly) {
            return;
        }

        $delay = rand(5, 120);
        $this->info("Pausing for {$delay} seconds...");
        sleep($delay);
    }
}
