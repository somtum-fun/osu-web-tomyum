<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace Tests\Controllers\Panel;

use App\Models\Rank;
use App\Models\User;
use Tests\TestCase;

class TitlesControllerTest extends TestCase
{
    public function testEditKeepsHoldersAndClearsLink(): void
    {
        $rank = $this->title('Old name', 'https://example.com');
        $player = User::factory()->create(['user_rank' => $rank->getKey()]);

        $this->actAsUser(User::factory()->withGroup('admin')->create(), true)
            ->post(route('panel.admin.titles.update', ['title' => $rank->getKey()]), ['title' => 'New name', 'url' => ''])
            ->assertRedirect(route('panel.admin.titles.index'));

        $rank->refresh();
        $this->assertSame('New name', $rank->rank_title);
        $this->assertNull($rank->url);
        $this->assertSame($rank->getKey(), $player->fresh()->getRawAttribute('user_rank'));
    }

    public function testBadLinkIsRefused(): void
    {
        $rank = $this->title('Linked', 'https://example.com');

        $this->actAsUser(User::factory()->withGroup('admin')->create(), true)
            ->post(route('panel.admin.titles.update', ['title' => $rank->getKey()]), ['title' => 'Linked', 'url' => 'javascript:alert(1)']);

        $this->assertSame('https://example.com', $rank->fresh()->url);
    }

    public function testGroupTitleKeepsItsNameButNotItsLink(): void
    {
        $rank = Rank::where('rank_special', 1)->where('rank_title', 'Featured Artist')->first() ?? $this->title('Featured Artist');
        $admin = User::factory()->withGroup('admin')->create();

        $this->actAsUser($admin, true)
            ->post(route('panel.admin.titles.update', ['title' => $rank->getKey()]), ['title' => 'Renamed', 'url' => '']);
        $this->assertSame('Featured Artist', $rank->fresh()->rank_title);

        $this->actAsUser($admin, true)
            ->post(route('panel.admin.titles.update', ['title' => $rank->getKey()]), ['title' => 'Featured Artist', 'url' => 'https://example.com']);
        $this->assertSame('https://example.com', $rank->fresh()->url);
    }

    public function testAnotherTitleCantTakeAGroupTitlesName(): void
    {
        $rank = $this->title('Ordinary');

        $this->actAsUser(User::factory()->withGroup('admin')->create(), true)
            ->post(route('panel.admin.titles.update', ['title' => $rank->getKey()]), ['title' => 'Tournament Committee', 'url' => '']);

        $this->assertSame('Ordinary', $rank->fresh()->rank_title);
    }

    public function testNonAdminCantEdit(): void
    {
        $rank = $this->title('Safe');

        $this->actAsUser(User::factory()->create(), true)
            ->post(route('panel.admin.titles.update', ['title' => $rank->getKey()]), ['title' => 'Hacked', 'url' => ''])
            ->assertStatus(403);

        $this->assertSame('Safe', $rank->fresh()->rank_title);
    }

    private function title(string $name, ?string $url = null): Rank
    {
        return Rank::create(['rank_title' => $name, 'rank_min' => 0, 'rank_special' => 1, 'rank_image' => '', 'url' => $url]);
    }
}
