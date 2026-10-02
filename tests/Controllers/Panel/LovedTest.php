<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace Tests\Controllers\Panel;

use App\Libraries\TomYumLoved;
use App\Models\Beatmapset;
use App\Models\Forum\Forum;
use App\Models\Forum\Topic;
use App\Models\TomYumLovedNomination;
use App\Models\TomYumLovedRound;
use App\Models\User;
use Http;
use Illuminate\Http\Client\Request as ClientRequest;
use Tests\TestCase;

class LovedTest extends TestCase
{
    private User $captain;

    protected function setUp(): void
    {
        parent::setUp();

        // TomYum's staff log and status changes (no maintenance: the pages' layout asks).
        Http::fake(fn (ClientRequest $request) => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/maintenance')
            ? Http::response(null, 404)
            : Http::response([]));
        config_set('osu.legacy.shared_interop_secret', 'test');
        config_set('osu.legacy.bancho_bot_user_id', User::factory()->create()->getKey());
        config_set('osu.forum.minimum_plays', 0);
        config_set('tomyum.loved.forum_id', Forum::factory()->create()->getKey());

        $this->captain = User::factory()->withGroup('loved', ['osu'])->create();
    }

    public function testACaptainPicksOnlyTheirModes(): void
    {
        $round = TomYumLovedRound::create(['name' => 'Round #1']);
        $set = Beatmapset::factory()->withBeatmaps('osu')->create(['approved' => Beatmapset::STATES['graveyard']]);

        $this->actAsUser($this->captain, true)
            ->post(route('panel.loved.nominate', ['round' => $round->getKey()]), [
                'beatmapset' => "https://osu.ppy.sh/beatmapsets/{$set->getKey()}#osu/1",
                'mode' => '1',
            ])
            ->assertSessionHas('panel_status.type', 'error');

        $this->actAsUser($this->captain, true)
            ->post(route('panel.loved.nominate', ['round' => $round->getKey()]), [
                'beatmapset' => (string) $set->getKey(),
                'mode' => '0',
                'description' => 'A [b]classic[/b].',
            ])
            ->assertSessionHas('panel_status.type', 'success');

        $this->assertSame(1, $round->nominations()->count());

        $this->actAsUser($this->captain, true)->get(route('panel.loved.index'))->assertSuccessful()->assertSee('Round #1');
        $this->actAsUser($this->captain, true)
            ->get(route('panel.loved.show', ['round' => $round->getKey()]))
            ->assertSuccessful()
            ->assertSee($set->title)
            ->assertSee('A [b]classic[/b].', false);
    }

    public function testThePageIntroducesProjectLovedAndItsTeam(): void
    {
        $html = $this->get(route('loved.index'))
            ->assertSuccessful()
            ->assertSee('Project Loved gives the ones players love a leaderboard')
            // The questions, with this site's settings (here, no plays needed to vote).
            ->assertSee('How does voting work?')
            ->assertSee('not restricted.')
            ->assertSee('The Loved team')
            ->getContent();

        // osu-web's user card for each member.
        preg_match('#data-react="user-card" data-user="([^"]*)"#', $html, $card);
        $user = json_decode(html_entity_decode($card[1], ENT_QUOTES | ENT_HTML5), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($this->captain->username, $user['username']);
    }

    public function testAPickingRoundIsntPublic(): void
    {
        $round = TomYumLovedRound::create(['name' => 'Secret round']);

        $this->get(route('loved.index'))->assertSuccessful()->assertDontSee('Secret round');
        $this->get(route('loved.rounds.show', ['round' => $round->getKey()]))->assertNotFound();
        // Links from before rounds had their own pages go there.
        $this->get(route('loved.index', ['round' => $round->getKey()]))->assertRedirect(route('loved.rounds.show', ['round' => $round->getKey()]));
    }

    public function testVotingAndClosingLovesTheMapsThatPassed(): void
    {
        $round = TomYumLovedRound::create(['name' => 'Round #2', 'threshold' => 60, 'min_votes' => 2]);
        $passing = Beatmapset::factory()->withBeatmaps('osu')->create(['approved' => Beatmapset::STATES['pending']]);
        $failing = Beatmapset::factory()->withBeatmaps('osu')->create(['approved' => Beatmapset::STATES['graveyard']]);
        foreach ([$passing, $failing] as $set) {
            $round->nominations()->create(['beatmapset_id' => $set->getKey(), 'ruleset_id' => 0, 'captain_id' => $this->captain->getKey()]);
        }

        $this->actAsUser($this->captain, true)
            ->post(route('panel.loved.open', ['round' => $round->getKey()]), ['days' => '7'])
            ->assertSessionHas('panel_status.type', 'success');
        $this->assertTrue($round->fresh()->isVoting());
        [$passingNomination, $failingNomination] = $round->nominations()->orderBy('id')->get()->all();
        $this->assertNotNull($passingNomination->topic_id);
        $this->assertSame('Should this map be Loved?', Topic::find($passingNomination->topic_id)->poll_title);

        // The overview sums the round up; its page has the maps' cards and polls.
        $this->get(route('loved.index'))->assertSuccessful()->assertSee('Round #2')->assertSee('Vote now')->assertSee($passing->title);
        $this->get(route('loved.rounds.show', ['round' => $round->getKey()]))
            ->assertSuccessful()
            ->assertSee('data-react="beatmapset-panel"', false)
            ->assertSee('No votes yet');

        foreach (['yes', 'yes', 'no'] as $vote) {
            $this->actAsUser(User::factory()->create(), true)
                ->post(route('loved.vote', ['nomination' => $passingNomination->getKey()]), ['vote' => $vote])
                ->assertRedirect();
        }
        $voter = User::factory()->create();
        $this->actAsUser($voter, true)->post(route('loved.vote', ['nomination' => $failingNomination->getKey()]), ['vote' => 'yes']);
        // Changing a vote replaces it.
        $this->actAsUser($voter, true)->post(route('loved.vote', ['nomination' => $failingNomination->getKey()]), ['vote' => 'no']);
        $this->assertSame([0, 1], TomYumLoved::votes($failingNomination->fresh()));

        // Jobs run in the request here, like on the site: making the covers fails (no mirror), which
        // mustn't undo the love.
        $this->actAsUser($this->captain, true)
            ->post(route('panel.loved.close', ['round' => $round->getKey()]))
            ->assertSessionHas('panel_status.type', 'success');

        $this->assertTrue($round->fresh()->isClosed());
        $passingNomination = $passingNomination->fresh();
        $this->assertTrue($passingNomination->passed);
        $this->assertSame([2, 1], [$passingNomination->result_yes, $passingNomination->result_no]);
        $this->assertFalse(TomYumLovedNomination::find($failingNomination->getKey())->passed);
        $this->assertTrue($passing->fresh()->isLoved());
        $this->assertFalse($failing->fresh()->isLoved());

        $topic = Topic::find($passingNomination->topic_id);
        $this->assertTrue($topic->isLocked());
        $this->assertFalse($topic->poll()->isOpen());
        $this->assertStringContainsString('Passed', $topic->posts()->orderByDesc('post_id')->first()->post_text);
    }
}
