<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace Tests\Controllers\Panel;

use App\Libraries\TomYumAssets;
use App\Models\ProfileBanner;
use App\Models\Tournament;
use App\Models\TournamentBanner;
use App\Models\User;
use Http;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AssetsAndBannersTest extends TestCase
{
    private User $admin;

    /** The game server's answers: a folder's images, by folder. */
    private array $folders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->withGroup('admin')->create();
        config_set('osu.legacy.shared_interop_secret', 'test'); // the game server's admin API is signed
        Http::fake(function (ClientRequest $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'GET' && str_ends_with($path, '/assets')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $images = array_map(fn ($name) => [
                    'path' => "{$query['folder']}/{$name}",
                    'name' => $name,
                    'normal' => ['bytes' => 1000, 'width' => 86, 'height' => 40],
                    'retina' => ['bytes' => 2000, 'width' => 172, 'height' => 80],
                ], $this->folders[$query['folder']] ?? []);

                return Http::response(['roots' => [], 'folder' => ['path' => $query['folder'], 'folders' => [], 'images' => $images]]);
            }

            return Http::response(['image' => $request['image'] ?? null]);
        });
    }

    public function testTheAssetsPage_ShowsPairs_AndUploadsKeepTheirName(): void
    {
        $this->folders['profile-badges'] = ['ndc-2023.png'];

        $this->actAsUser($this->admin, true)
            ->get(route('panel.admin.assets.index', ['folder' => 'profile-badges']))
            ->assertSuccessful()
            ->assertSee('ndc-2023.png')
            ->assertSee(TomYumAssets::url('profile-badges/ndc-2023@2x.png'), false);

        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.assets.store'), [
                'folder' => 'profile-badges',
                'name' => 'Winter Cup 2026',
                'retina' => UploadedFile::fake()->image('anything.png', 172, 80),
            ])
            ->assertSessionHas('panel_status.type', 'success');

        Http::assertSent(fn (ClientRequest $request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/assets')
            && $request->method() === 'POST'
            && ($request->data()['path'] ?? null) === 'profile-badges/Winter-Cup-2026.png'
            && ($request->data()['normal'] ?? null) === null
            && ($request->data()['retina'] ?? null) !== null);
    }

    public function testOnlyTheManagedFolders(): void
    {
        $this->actAsUser($this->admin, true)
            ->get(route('panel.admin.assets.index', ['folder' => 'profile-badges/../../etc']))
            ->assertStatus(404);
        $this->actAsUser($this->admin, true)
            ->get(route('panel.admin.assets.index', ['folder' => 'replays']))
            ->assertStatus(404);

        // The TC has badges and banners, not medal icons.
        $tc = User::factory()->withGroup('tc')->create();
        $this->actAsUser($tc, true)
            ->get(route('panel.tc.assets.index', ['folder' => 'medals/client']))
            ->assertStatus(404);
    }

    public function testABadgeIsGivenFromAnUploadedImage_UnderTheNameGiven(): void
    {
        $player = User::factory()->create();

        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.badges.store', ['user' => $player->getKey()]), [
                'retina_file' => UploadedFile::fake()->image('whatever@2x.png', 172, 80),
                'name' => 'ndc-2023',
                'description' => 'NDC 2023',
                'awarded' => now()->toDateString(),
            ])
            ->assertSessionHas('panel_status.type', 'success');

        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/assets')
            && ($request->data()['path'] ?? null) === 'profile-badges/ndc-2023.png');
        Http::assertSent(fn (ClientRequest $request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), "/users/{$player->getKey()}/badges")
            && ($request->data()['image'] ?? null) === 'ndc-2023.png');
    }

    public function testATournamentsBanners_AreItsFolder_AndAPlayerGetsTheirCountrys(): void
    {
        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.tournament-banners.store'), ['name' => 'osu! World Cup 2025', 'folder' => 'tournament-banners/official/owc2025/profile'])
            ->assertSessionHas('panel_status.type', 'success');
        $banner = TournamentBanner::latest('tournament_id')->firstOrFail();
        $this->assertSame(TomYumAssets::baseUrl().'/tournament-banners/official/owc2025/profile/', $banner->banner_url_prefix);

        $player = User::factory()->create();
        $country = $player->country_acronym;
        $this->folders['tournament-banners/official/owc2025/profile'] = ["supporter_{$country}.jpg", 'supporter_ZZ.jpg', 'winner_PL.jpg'];
        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.tournament-banners.give', ['banner' => $banner->getKey()]), ['user' => $player->username, 'image' => '*country'])
            ->assertSessionHas('panel_status.type', 'success');

        $given = ProfileBanner::where('user_id', $player->getKey())->firstOrFail();
        $this->assertSame("supporter_{$country}", $given->country_acronym);
        $this->assertSame(TomYumAssets::baseUrl()."/tournament-banners/official/owc2025/profile/supporter_{$country}.jpg", $given->image());

        // Another of its images for them: they have both (winner and supporter).
        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.tournament-banners.give', ['banner' => $banner->getKey()]), ['user' => $player->username, 'image' => 'winner_PL']);
        $this->assertSame(2, ProfileBanner::where('user_id', $player->getKey())->count());

        // Taking one away leaves the other.
        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.tournament-banners.take', ['banner' => $banner->getKey(), 'user' => $player->getKey()]), ['image' => 'winner_PL']);
        $this->assertSame(["supporter_{$country}"], ProfileBanner::where('user_id', $player->getKey())->pluck('country_acronym')->all());

        // One that isn't in its folder isn't given.
        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.tournament-banners.give', ['banner' => $banner->getKey()]), ['user' => $player->username, 'image' => 'made_up'])
            ->assertSessionHas('panel_status.type', 'error');
    }

    public function testABannerMadeBefore_MovesToTheAssetsHost_AndItsHoldersKeepIt(): void
    {
        $tournament = Tournament::create([
            'name' => 'Old Cup', 'description' => '', 'play_mode' => 0,
            'signup_open' => now(), 'signup_close' => now(), 'start_date' => now(), 'end_date' => now(),
        ]);
        $dir = public_path("uploads/tournament-banners/{$tournament->getKey()}");
        @mkdir($dir, 0755, true);
        file_put_contents("{$dir}/banner.jpg", UploadedFile::fake()->image('a.jpg', 1000, 60)->getContent());
        file_put_contents("{$dir}/banner@2x.jpg", UploadedFile::fake()->image('a.jpg', 2000, 120)->getContent());
        $banner = TournamentBanner::create([
            'tournament_id' => $tournament->getKey(),
            'is_active' => true,
            'banner_url_prefix' => url("/uploads/tournament-banners/{$tournament->getKey()}/banner"),
        ]);
        $holder = User::factory()->create();
        ProfileBanner::create(['user_id' => $holder->getKey(), 'tournament_id' => $tournament->getKey(), 'country_acronym' => '']);

        $this->actAsUser($this->admin, true)
            ->post(route('panel.admin.tournament-banners.move', ['banner' => $banner->getKey()]), ['folder' => 'tomyum/old-cup'])
            ->assertSessionHas('panel_status.type', 'success');

        Http::assertSent(fn (ClientRequest $request) => ($request->data()['path'] ?? null) === 'tournament-banners/tomyum/old-cup/banner.jpg'
            && ($request->data()['normal'] ?? null) !== null && ($request->data()['retina'] ?? null) !== null);
        $this->assertSame(TomYumAssets::baseUrl().'/tournament-banners/tomyum/old-cup/', $banner->fresh()->banner_url_prefix);
        $this->assertSame(
            TomYumAssets::baseUrl().'/tournament-banners/tomyum/old-cup/banner.jpg',
            ProfileBanner::where('user_id', $holder->getKey())->firstOrFail()->image(),
        );
        $this->assertFileDoesNotExist("{$dir}/banner.jpg");
    }
}
