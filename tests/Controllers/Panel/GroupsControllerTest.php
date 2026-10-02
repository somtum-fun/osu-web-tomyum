<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace Tests\Controllers\Panel;

use App\Models\User;
use Http;
use Tests\TestCase;

class GroupsControllerTest extends TestCase
{
    public function testAdminsWriteWhatAGroupDoes_AndItsPageShowsIt(): void
    {
        Http::fake(); // TomYum's staff log
        $admin = User::factory()->withGroup('admin')->create();
        $group = app('groups')->byIdentifier('loved');

        $this->actAsUser($admin, true)
            ->get(route('panel.admin.groups.index'))
            ->assertSuccessful()
            ->assertSee($group->group_name);

        $this->actAsUser($admin, true)
            ->post(route('panel.admin.groups.update', ['group' => $group->getKey()]), ['description' => "Pick the **Loved** maps.\r\n"])
            ->assertSessionHas('panel_status.type', 'success');
        $this->assertSame('Pick the **Loved** maps.', $group->fresh()->group_desc);
        $this->assertSame('Pick the **Loved** maps.', app('groups')->byIdentifier('loved')->group_desc);

        // Emptied: no description.
        $this->actAsUser($admin, true)->post(route('panel.admin.groups.update', ['group' => $group->getKey()]), ['description' => ' ']);
        $this->assertNull($group->fresh()->group_desc);

        $this->actAsUser(User::factory()->create(), true)->get(route('panel.admin.groups.index'))->assertForbidden();
    }
}
