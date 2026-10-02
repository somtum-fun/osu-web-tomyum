<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace Tests\Controllers;

use App\Libraries\TomYumLoved;
use App\Models\Beatmapset;
use App\Models\TomYumLovedRequest;
use App\Models\User;
use Http;
use Illuminate\Http\Client\Request as ClientRequest;
use Tests\TestCase;

class LovedRequestsTest extends TestCase
{
    private Beatmapset $set;

    protected function setUp(): void
    {
        parent::setUp();

        // TomYum's admin API: no maintenance, osu! has no set the server lacks, statuses taken.
        Http::fake(fn (ClientRequest $request) => match (true) {
            str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/maintenance') => Http::response(null, 404),
            str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/import') => Http::response(['error' => 'not found'], 404),
            default => Http::response([]),
        });
        config_set('osu.legacy.shared_interop_secret', 'test');
        $this->set = Beatmapset::factory()->withBeatmaps('osu')->create(['approved' => Beatmapset::STATES['graveyard']]);
    }

    public function testAskingTwiceSendsThePlayerToTheRequestToReviewIt(): void
    {
        $first = User::factory()->create();
        $this->actAsUser($first, true)
            ->post(route('loved.requests.store'), ['beatmapset' => "https://osu.ppy.sh/beatmapsets/{$this->set->getKey()}", 'mode' => '0', 'reason' => 'Everyone plays this one.'])
            ->assertSessionHas('loved_status.type', 'success');
        $request = TomYumLovedRequest::sole();
        $this->assertSame(1, $request->score);

        $second = User::factory()->create();
        $this->actAsUser($second, true)
            ->post(route('loved.requests.store'), ['beatmapset' => (string) $this->set->getKey(), 'mode' => '0', 'reason' => 'Yes please, it is great.'])
            ->assertRedirect(route('loved.requests.show', ['request' => $request->getKey()]))
            ->assertSessionHas('loved_status.type', 'error');
        $this->assertSame(1, TomYumLovedRequest::count());

        $this->get(route('loved.index', ['mode' => 'osu']))
            ->assertSuccessful()
            ->assertSee('Everyone plays this one.')
            ->assertSee('data-react="beatmapset-panel"', false);
    }

    public function testReviewsMakeTheScore(): void
    {
        $asker = User::factory()->create();
        [, , $request] = TomYumLoved::request($asker, (string) $this->set->getKey(), 0, 'Everyone plays this one.');

        $for = User::factory()->create();
        $against = User::factory()->create();
        $this->actAsUser($for, true)
            ->post(route('loved.requests.review', ['request' => $request->getKey()]), ['score' => '1', 'comment' => 'Fun map.'])
            ->assertSessionHas('loved_status.type', 'success');
        $this->actAsUser($against, true)
            ->post(route('loved.requests.review', ['request' => $request->getKey()]), ['score' => '-1', 'comment' => 'Too short.'])
            ->assertSessionHas('loved_status.type', 'success');
        $this->assertSame(1, $request->fresh()->score);

        // Changing a review, and the asker can't review their own request.
        $this->actAsUser($against, true)
            ->post(route('loved.requests.review', ['request' => $request->getKey()]), ['score' => '1', 'comment' => 'Changed my mind.']);
        $this->assertSame(3, $request->fresh()->score);
        $this->actAsUser($asker, true)
            ->post(route('loved.requests.review', ['request' => $request->getKey()]), ['score' => '1', 'comment' => 'Mine!'])
            ->assertSessionHas('loved_status.type', 'error');

        $this->get(route('loved.requests.show', ['request' => $request->getKey()]))
            ->assertSuccessful()
            ->assertSee('Changed my mind.');
    }

    public function testMapsThatCantBeLovedArentTaken(): void
    {
        $user = User::factory()->create();
        $ranked = Beatmapset::factory()->withBeatmaps('osu')->ranked()->create();

        foreach ([
            [(string) $ranked->getKey(), '0', 'ranked', 'A long enough reason.'],
            [(string) $this->set->getKey(), '1', 'no osu!taiko', 'A long enough reason.'],
            ['999999999', '0', "osu! doesn't have", 'A long enough reason.'],
            [(string) $this->set->getKey(), '0', '10 to 1000', 'short'],
        ] as [$set, $mode, $message, $reason]) {
            $this->actAsUser($user, true)
                ->post(route('loved.requests.store'), ['beatmapset' => $set, 'mode' => $mode, 'reason' => $reason])
                ->assertSessionHas('loved_status.type', 'error');
            $this->assertStringContainsString($message, session('loved_status.message'));
        }
        $this->assertSame(0, TomYumLovedRequest::count());
    }

    public function testTheTeamDecides(): void
    {
        $captain = User::factory()->withGroup('loved', ['osu'])->create();
        $taikoCaptain = User::factory()->withGroup('loved', ['taiko'])->create();
        [, , $loved] = TomYumLoved::request(User::factory()->create(), (string) $this->set->getKey(), 0, 'Love me please.');
        $other = Beatmapset::factory()->withBeatmaps('osu')->create(['approved' => Beatmapset::STATES['pending']]);
        [, , $rejected] = TomYumLoved::request(User::factory()->create(), (string) $other->getKey(), 0, 'And me too please.');

        // Only the team for the request's mode, and with a reason.
        $this->actAsUser($taikoCaptain, true)
            ->post(route('loved.requests.decide', ['request' => $loved->getKey()]), ['action' => 'love', 'reason' => 'Yes.'])
            ->assertStatus(403);
        $this->actAsUser($captain, true)
            ->post(route('loved.requests.decide', ['request' => $loved->getKey()]), ['action' => 'love', 'reason' => ''])
            ->assertSessionHas('loved_status.type', 'error');

        $this->actAsUser($captain, true)
            ->post(route('loved.requests.decide', ['request' => $loved->getKey()]), ['action' => 'love', 'reason' => 'Players clearly want it.'])
            ->assertSessionHas('loved_status.type', 'success');
        $this->assertSame('loved', $loved->fresh()->status);
        $this->assertTrue($this->set->fresh()->isLoved());

        $this->actAsUser($captain, true)
            ->post(route('loved.requests.decide', ['request' => $rejected->getKey()]), ['action' => 'reject', 'reason' => 'The mapper asked us not to.'])
            ->assertSessionHas('loved_status.type', 'success');
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->get(route('loved.index', ['status' => 'rejected']))->assertSee('The mapper asked us not to.');

        // Rejected: not asked for again.
        [$ok, $message] = TomYumLoved::request(User::factory()->create(), (string) $other->getKey(), 0, 'Please reconsider it.');
        $this->assertFalse($ok);
        $this->assertStringContainsString('rejected', $message);
    }

    public function testBlacklistedMapsCantBeAskedFor(): void
    {
        $captain = User::factory()->withGroup('loved', ['osu'])->create();
        [, , $request] = TomYumLoved::request(User::factory()->create(), (string) $this->set->getKey(), 0, 'Love me please.');

        $this->actAsUser($captain, true)
            ->post(route('loved.blacklist.store'), ['beatmapset' => (string) $this->set->getKey(), 'reason' => 'Inappropriate background.'])
            ->assertSessionHas('loved_status.type', 'success');
        $this->assertSame('removed', $request->fresh()->status);

        $this->actAsUser(User::factory()->create(), true)
            ->post(route('loved.requests.store'), ['beatmapset' => (string) $this->set->getKey(), 'mode' => '0', 'reason' => 'Please love this one.'])
            ->assertSessionHas('loved_status.type', 'error');
        $this->assertStringContainsString('Inappropriate background.', session('loved_status.message'));

        // Not for players.
        $this->actAsUser(User::factory()->create(), true)
            ->post(route('loved.blacklist.store'), ['beatmapset' => '1', 'reason' => 'Nope nope.'])
            ->assertStatus(403);
    }
}
