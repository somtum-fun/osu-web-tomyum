<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace Tests\Controllers;

use App\Libraries\TomYumLoved;
use App\Models\Beatmapset;
use App\Models\TomYumLovedRequest;
use App\Models\TomYumLovedRound;
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

        // TomYum's admin API: no maintenance, osu! has no set the server lacks.
        Http::fake(fn (ClientRequest $request) => match (true) {
            str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/maintenance') => Http::response(null, 404),
            str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/import') => Http::response(['error' => 'not found'], 404),
            default => Http::response([]),
        });
        config_set('osu.legacy.shared_interop_secret', 'test');
        $this->set = Beatmapset::factory()->withBeatmaps('osu')->create(['approved' => Beatmapset::STATES['graveyard']]);
    }

    public function testAskingTwiceForAMapSupportsTheFirstRequest(): void
    {
        $first = User::factory()->create();
        $this->actAsUser($first, true)
            ->post(route('loved.requests.store'), ['beatmapset' => "https://osu.ppy.sh/beatmapsets/{$this->set->getKey()}", 'mode' => '0', 'reason' => 'Everyone plays this one.'])
            ->assertSessionHas('loved_status.type', 'success');

        $second = User::factory()->create();
        $this->actAsUser($second, true)
            ->post(route('loved.requests.store'), ['beatmapset' => (string) $this->set->getKey(), 'mode' => '0', 'reason' => 'Yes please, it is great.'])
            ->assertSessionHas('loved_status.type', 'success');

        $request = TomYumLovedRequest::sole();
        $this->assertSame($first->getKey(), $request->user_id);
        $this->assertSame(2, $request->supporters_count);

        $this->get(route('loved.requests', ['mode' => 'osu']))
            ->assertSuccessful()
            ->assertSee('Everyone plays this one.')
            ->assertSee('data-react="beatmapset-panel"', false);

        // Taking support back; the last one takes the request with it.
        $this->actAsUser($second, true)->post(route('loved.requests.support', ['request' => $request->getKey()]), ['support' => '0']);
        $this->assertSame(1, $request->fresh()->supporters_count);
        $this->actAsUser($first, true)->post(route('loved.requests.support', ['request' => $request->getKey()]), ['support' => '0']);
        $this->assertNull($request->fresh());
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

    public function testTheTeamPicksOrDeclinesRequests(): void
    {
        $captain = User::factory()->withGroup('loved', ['osu'])->create();
        $round = TomYumLovedRound::create(['name' => 'Round #1']);
        [, , $picked] = TomYumLoved::request(User::factory()->create(), (string) $this->set->getKey(), 0, 'Pick me please.');
        $other = Beatmapset::factory()->withBeatmaps('osu')->create(['approved' => Beatmapset::STATES['pending']]);
        [, , $declined] = TomYumLoved::request(User::factory()->create(), (string) $other->getKey(), 0, 'And me too please.');

        $this->actAsUser($captain, true)
            ->get(route('panel.loved.show', ['round' => $round->getKey()]))
            ->assertSuccessful()
            ->assertSee('Pick me please.');

        $this->actAsUser($captain, true)
            ->post(route('panel.loved.requests.pick', ['round' => $round->getKey(), 'request' => $picked->getKey()]))
            ->assertSessionHas('panel_status.type', 'success');
        $this->assertSame('picked', $picked->fresh()->status);
        $this->assertSame($this->set->getKey(), $round->nominations()->sole()->beatmapset_id);

        $this->actAsUser($captain, true)
            ->post(route('panel.loved.requests.decline', ['round' => $round->getKey(), 'request' => $declined->getKey()]), ['reason' => 'The mapper asked us not to.'])
            ->assertSessionHas('panel_status.type', 'success');
        $this->assertSame('declined', $declined->fresh()->status);

        // Declined lately: shown, and not asked for again.
        $this->get(route('loved.requests', ['mode' => 'osu']))->assertSee('The mapper asked us not to.');
        [$ok, $message] = TomYumLoved::request(User::factory()->create(), (string) $other->getKey(), 0, 'Please reconsider it.');
        $this->assertFalse($ok);
        $this->assertStringContainsString('declined', $message);
    }
}
