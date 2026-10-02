<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace Tests\Controllers\Panel;

use App\Http\Controllers\Panel\BnController;
use App\Models\Artist;
use App\Models\ArtistTrack;
use App\Models\Beatmapset;
use App\Models\User;
use Http;
use Tests\TestCase;

class BnSetsTrackTest extends TestCase
{
    public function testAnUploadedSetGetsAFeaturedArtistTrack_AndLosesIt(): void
    {
        Http::fake(); // TomYum's staff log
        $admin = User::factory()->withGroup('admin')->create();
        $set = Beatmapset::factory()->create(['beatmapset_id' => BnController::CUSTOM_ID_FLOOR + 5]);
        $track = ArtistTrack::factory()->create(['artist_id' => Artist::factory()->create()->getKey()]);

        $this->actAsUser($admin, true)
            ->post(route('panel.bn.sets.track', ['set' => $set->getKey()]), ['track_id' => (string) $track->getKey()])
            ->assertSessionHas('panel_status.type', 'success');
        $this->assertSame($track->getKey(), $set->fresh()->track_id);

        $this->actAsUser($admin, true)
            ->post(route('panel.bn.sets.track', ['set' => $set->getKey()]))
            ->assertSessionHas('panel_status.type', 'success');
        $this->assertNull($set->fresh()->track_id);
    }

    public function testOsusSetsKeepOsusTrack(): void
    {
        Http::fake();
        $admin = User::factory()->withGroup('admin')->create();
        $set = Beatmapset::factory()->create(['beatmapset_id' => 1143822]);
        $track = ArtistTrack::factory()->create(['artist_id' => Artist::factory()->create()->getKey()]);

        $this->actAsUser($admin, true)
            ->post(route('panel.bn.sets.track', ['set' => $set->getKey()]), ['track_id' => (string) $track->getKey()])
            ->assertSessionHas('panel_status.type', 'error');
        $this->assertNull($set->fresh()->track_id);
    }
}
