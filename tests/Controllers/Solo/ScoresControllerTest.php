<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace Tests\Controllers\Solo;

use App\Models\Beatmap;
use App\Models\Build;
use App\Models\ScoreToken;
use App\Models\Solo\Score;
use App\Models\User;
use LaravelRedis;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScoresControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // TomYum: the ranked mods list is cached a minute; each test starts from the database's.
        \Cache::forget('tomyum_ranked_mods:0');
    }

    public function testStore()
    {
        $build = Build::factory()->create(['allow_ranking' => true]);
        $scoreToken = ScoreToken::factory()->create(['build_id' => $build]);

        $this->expectCountChange(fn () => Score::count(), 1);
        $this->expectCountChange(fn () => $this->processingQueueCount(), 1);
        $this->expectCountChange(
            fn () => \LaravelRedis::llen($GLOBALS['cfg']['osu']['client']['token_queue']),
            1,
        );

        $this->withHeaders(['x-token' => static::createClientToken($build)]);
        $this->actAsScopedUser($scoreToken->user, ['*']);

        $this->json(
            'PUT',
            route('api.beatmaps.solo.scores.store', [
                'beatmap' => $scoreToken->beatmap->getKey(),
                'token' => $scoreToken->getKey(),
            ]),
            [
                'accuracy' => 1,
                'max_combo' => 10,
                'mods' => [
                    ['acronym' => 'DT'],
                ],
                'passed' => true,
                'rank' => 'A',
                'statistics' => ['Good' => 1],
                'total_score' => 10,
            ]
        )->assertSuccessful()
        ->assertJsonFragment(['build_id' => $scoreToken->build_id]);

        $score = $scoreToken->fresh()->score;
        $this->assertNotNull($score);
        $this->assertSame(1, count($score->data->mods));
    }

    public static function dataProviderForTestStoreRelaxIsNotRanked(): array
    {
        return [
            // Without the ranked mods list: only Relax and Autopilot are left out.
            'no mods' => [[], true],
            'DT' => [[['acronym' => 'DT']], true],
            'RX' => [[['acronym' => 'RX']], false],
            'AP' => [[['acronym' => 'AP']], false],
            // With the ranked mods list: osu!'s and staff's are ranked, the rest aren't.
            'DT, ranked by osu!' => [[['acronym' => 'DT']], true, true],
            'DA, not ranked' => [[['acronym' => 'DA']], false, true],
            'DA, ranked by staff' => [[['acronym' => 'DA']], true, true, ['DA']],
            'HD and DA' => [[['acronym' => 'HD'], ['acronym' => 'DA']], false, true],
        ];
    }

    /**
     * TomYum: Relax and Autopilot have their own leaderboards; on the vanilla ones (and in the
     * vanilla grade counts and ranked score) they don't count.
     */
    #[DataProvider('dataProviderForTestStoreRelaxIsNotRanked')]
    public function testStoreRelaxIsNotRanked(array $mods, bool $ranked, bool $withList = false, array $staffRanked = [])
    {
        // The score processor makes this table; the test database has it from here on (its rows are
        // each test's own: they're emptied first, and rolled back after).
        if (!\Schema::hasTable('tomyum_ranked_mods')) {
            \DB::statement('CREATE TABLE tomyum_ranked_mods (ruleset_id tinyint unsigned NOT NULL, acronym varchar(8) NOT NULL, osu_ranked tinyint(1) NOT NULL DEFAULT 0, ranked tinyint(1) NOT NULL DEFAULT 0, applied tinyint(1) NOT NULL DEFAULT 0, PRIMARY KEY (ruleset_id, acronym))');
        }
        \DB::table('tomyum_ranked_mods')->delete();
        \Cache::forget('tomyum_ranked_mods:0');
        if ($withList) {
            foreach (['DT' => true, 'HD' => true, 'DA' => false, 'RX' => false] as $acronym => $osuRanked) {
                \DB::table('tomyum_ranked_mods')->insert([
                    'ruleset_id' => 0,
                    'acronym' => $acronym,
                    'osu_ranked' => $osuRanked,
                    'ranked' => in_array($acronym, $staffRanked, true),
                ]);
            }
        }

        $build = Build::factory()->create(['allow_ranking' => true]);
        $scoreToken = ScoreToken::factory()->create([
            'beatmap_id' => Beatmap::factory()->ranked()->state(['playmode' => 0]),
            'build_id' => $build,
        ]);

        $this->withHeaders(['x-token' => static::createClientToken($build)]);
        $this->actAsScopedUser($scoreToken->user, ['*']);

        $this->json(
            'PUT',
            route('api.beatmaps.solo.scores.store', [
                'beatmap' => $scoreToken->beatmap->getKey(),
                'token' => $scoreToken->getKey(),
            ]),
            [
                'accuracy' => 1,
                'max_combo' => 10,
                'mods' => $mods,
                'passed' => true,
                'rank' => 'A',
                'statistics' => ['Good' => 1],
                'total_score' => 10,
            ]
        )->assertSuccessful();

        $this->assertSame($ranked, $scoreToken->fresh()->score->ranked);
    }

    public function testStoreCompleted()
    {
        $scoreToken = ScoreToken::factory()->create();
        $score = Score::factory()->create(['beatmap_id' => $scoreToken->beatmap_id]);
        $scoreToken->update(['score_id' => $score->getKey()]);

        $this->expectCountChange(fn () => Score::count(), 0);
        $this->expectCountChange(fn () => $this->processingQueueCount(), 0);

        $this->actAsScopedUser($scoreToken->user, ['*']);

        $this->json(
            'PUT',
            route('api.beatmaps.solo.scores.store', [
                'beatmap' => $scoreToken->beatmap->getKey(),
                'token' => $scoreToken->getKey(),
            ]),
            [
                'accuracy' => 1,
                'max_combo' => 10,
                'passed' => true,
                'rank' => 'A',
                'statistics' => ['Good' => 1],
                'total_score' => 10,
            ]
        )->assertStatus(200);

        $this->assertSame($score->getKey(), $scoreToken->fresh()->score_id);
    }

    public function testStoreMissingData()
    {
        $scoreToken = ScoreToken::factory()->create();

        $this->expectCountChange(fn () => Score::count(), 0);

        $this->actAsScopedUser($scoreToken->user, ['*']);

        $this->json(
            'PUT',
            route('api.beatmaps.solo.scores.store', [
                'beatmap' => $scoreToken->beatmap->getKey(),
                'token' => $scoreToken->getKey(),
            ]),
            [
                'rank' => 'A',
            ]
        )->assertStatus(422);
    }

    public function testStoreUpdatedBeatmap()
    {
        $build = Build::factory()->create(['allow_ranking' => true]);
        $scoreToken = ScoreToken::factory()->create(['build_id' => $build]);
        $scoreToken->beatmap->beatmapset->update(['approved_date' => $scoreToken->created_at->addMinutes(5)]);

        $this->expectCountChange(fn () => Score::count(), 0);
        $this->expectCountChange(fn () => $this->processingQueueCount(), 0);
        $this->expectCountChange(
            fn () => \LaravelRedis::llen($GLOBALS['cfg']['osu']['client']['token_queue']),
            0,
        );

        $this->withHeaders(['x-token' => static::createClientToken($build)]);
        $this->actAsScopedUser($scoreToken->user, ['*']);

        $this->json(
            'PUT',
            route('api.beatmaps.solo.scores.store', [
                'beatmap' => $scoreToken->beatmap->getKey(),
                'token' => $scoreToken->getKey(),
            ]),
            [
                'accuracy' => 1,
                'max_combo' => 10,
                'mods' => [
                    ['acronym' => 'DT'],
                ],
                'passed' => true,
                'rank' => 'A',
                'statistics' => ['Good' => 1],
                'total_score' => 10,
            ]
        )->assertStatus(422);

        $score = $scoreToken->fresh()->score;
        $this->assertNull($score);
    }

    public function testStoreWrongUser()
    {
        $otherUser = User::factory()->create();
        $scoreToken = ScoreToken::factory()->create();
        $this->expectCountChange(fn () => Score::count(), 0);

        $this->actAsScopedUser($otherUser, ['*']);

        $this->json(
            'PUT',
            route('api.beatmaps.solo.scores.store', [
                'beatmap' => $scoreToken->beatmap->getKey(),
                'token' => $scoreToken->getKey(),
            ]),
            [
                'accuracy' => 1,
                'max_combo' => 10,
                'passed' => true,
                'rank' => 'A',
                'statistics' => ['Good' => 1],
                'total_score' => 10,
            ]
        )->assertStatus(404);
    }

    public function testStoreInvalidModCombination()
    {
        $scoreToken = ScoreToken::factory()->create();
        $this->expectCountChange(fn () => Score::count(), 0);

        $this->actAsScopedUser($scoreToken->user);

        $this->json(
            'PUT',
            route('api.beatmaps.solo.scores.store', [
                'beatmap' => $scoreToken->beatmap->getKey(),
                'token' => $scoreToken->getKey(),
            ]),
            [
                'accuracy' => 1,
                'max_combo' => 10,
                'mods' => [
                    ['acronym' => 'DT'],
                    ['acronym' => 'HT'],
                ],
                'passed' => true,
                'rank' => 'A',
                'statistics' => ['Good' => 1],
                'total_score' => 10,
            ]
        )->assertStatus(422);
    }

    public function testTotalScoreWithoutModsIsNotStoredWhenModsNotPresent()
    {
        $build = Build::factory()->create(['allow_ranking' => true]);
        $scoreToken = ScoreToken::factory()->create(['build_id' => $build]);

        $this->expectCountChange(fn () => Score::count(), 1);

        $this->withHeaders(['x-token' => static::createClientToken($build)]);
        $this->actAsScopedUser($scoreToken->user, ['*']);

        $this->json(
            'PUT',
            route('api.beatmaps.solo.scores.store', [
                'beatmap' => $scoreToken->beatmap->getKey(),
                'token' => $scoreToken->getKey(),
            ]),
            [
                'accuracy' => 1,
                'max_combo' => 10,
                'mods' => [],
                'passed' => true,
                'rank' => 'A',
                'statistics' => ['Good' => 1],
                'total_score' => 1000000,
                'total_score_without_mods' => 1000000,
            ]
        )->assertSuccessful()
            ->assertJsonFragment(['build_id' => $scoreToken->build_id]);

        $score = $scoreToken->fresh()->score;
        $this->assertNotNull($score);
        $this->assertNull($score->data->totalScoreWithoutMods);
    }

    public function testTotalScoreWithoutModsIsStoredWhenModsPresent()
    {
        $build = Build::factory()->create(['allow_ranking' => true]);
        $scoreToken = ScoreToken::factory()->create(['build_id' => $build]);

        $this->expectCountChange(fn () => Score::count(), 1);

        $this->withHeaders(['x-token' => static::createClientToken($build)]);
        $this->actAsScopedUser($scoreToken->user, ['*']);

        $this->json(
            'PUT',
            route('api.beatmaps.solo.scores.store', [
                'beatmap' => $scoreToken->beatmap->getKey(),
                'token' => $scoreToken->getKey(),
            ]),
            [
                'accuracy' => 1,
                'max_combo' => 10,
                'mods' => [
                    ['acronym' => 'DT'],
                ],
                'passed' => true,
                'rank' => 'A',
                'statistics' => ['Good' => 1],
                'total_score' => 1230000,
                'total_score_without_mods' => 1000000,
            ]
        )->assertSuccessful()
            ->assertJsonFragment(['build_id' => $scoreToken->build_id]);

        $score = $scoreToken->fresh()->score;
        $this->assertNotNull($score);
        $this->assertSame(1000000, $score->data->totalScoreWithoutMods);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        static::createApp();
        LaravelRedis::del($GLOBALS['cfg']['osu']['scores']['processing_queue']);
    }

    private function processingQueueCount(): int
    {
        return LaravelRedis::llen($GLOBALS['cfg']['osu']['scores']['processing_queue']);
    }
}
