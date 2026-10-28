<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace Tests\Controllers\Panel;

use App\Libraries\TomYumBranding;
use App\Models\User;
use Carbon\Carbon;
use Http;
use Tests\TestCase;

class BrandingControllerTest extends TestCase
{
    public function testFirstMembersBeforeConfiguresJoinDateCutoff(): void
    {
        Http::fake();
        $admin = User::factory()->withGroup('admin')->create();
        $julyUser = User::factory()->create([
            'user_regdate' => Carbon::createFromFormat('!Y-m-d H:i:s', '2024-07-15 12:00:00', 'UTC'),
        ]);
        $augustUser = User::factory()->create([
            'user_regdate' => Carbon::createFromFormat('!Y-m-d H:i:s', '2024-08-15 12:00:00', 'UTC'),
        ]);

        // Unset by default: nobody gets "Here since the beginning".
        $this->assertNull(TomYumBranding::firstMembersBefore());
        $this->assertStringNotContainsString('Here since the beginning', (string) display_regdate($julyUser));
        $this->assertStringContainsString('Joined', (string) display_regdate($julyUser));

        $this->actAsUser($admin, true)
            ->get(route('panel.admin.branding.index'))
            ->assertSuccessful()
            ->assertSee('Original players')
            ->assertSee('Now: <strong>off</strong>', false);

        $basePayload = [
            ...TomYumBranding::DEFAULTS,
            'downloads_show_lazer' => '1',
            'downloads_show_legacy' => '1',
            'downloads_show_stable' => '1',
            'favicon' => 'letter',
            'logo' => 'text',
            'vote_enabled' => '1',
            'vote_sites' => TomYumBranding::voteSitesText(TomYumBranding::defaultVoteSites()),
        ];

        // Invalid date is rejected without changing the setting.
        $this->actAsUser($admin, true)
            ->post(route('panel.admin.branding.update'), [
                ...$basePayload,
                'first_members_before' => '2024-02-30',
            ])
            ->assertSessionHas('panel_status.type', 'error');
        $this->assertNull(TomYumBranding::firstMembersBefore());

        // Setting 2024-08-01 enables "Here since the beginning" for accounts registered before August 2024.
        $this->actAsUser($admin, true)
            ->post(route('panel.admin.branding.update'), [
                ...$basePayload,
                'first_members_before' => '2024-08-01',
            ])
            ->assertSessionHas('panel_status.type', 'success');

        $this->assertSame('2024-08-01', TomYumBranding::firstMembersBefore());
        $this->assertStringContainsString('Here since the beginning', (string) display_regdate($julyUser));
        $this->assertStringNotContainsString('Here since the beginning', (string) display_regdate($augustUser));
        $this->assertStringContainsString('Joined', (string) display_regdate($augustUser));

        $this->actAsUser($admin, true)
            ->get(route('panel.admin.branding.index'))
            ->assertSuccessful()
            ->assertSee('value="2024-08-01"', false)
            ->assertSee('Now: <strong>before 2024-08-01 UTC</strong>', false);

        $this->get(route('users.show', ['user' => $julyUser->getKey()]))
            ->assertSuccessful()
            ->assertSee('var firstMembersBefore = "2024-08-01";', false);

        // Clearing the field turns it back off.
        $this->actAsUser($admin, true)
            ->post(route('panel.admin.branding.update'), [
                ...$basePayload,
                'first_members_before' => '',
            ])
            ->assertSessionHas('panel_status.type', 'success');

        $this->assertNull(TomYumBranding::firstMembersBefore());
        $this->assertStringNotContainsString('Here since the beginning', (string) display_regdate($julyUser));
    }
}
