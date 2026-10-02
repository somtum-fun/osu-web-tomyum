<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Models\Group;
use App\Models\UserGroup;
use Auth;
use Request;

/**
 * Admin panel: the site's groups and what each one does (its description, Markdown, shown above its
 * members on its page). Who is in a group is set on the player's account page.
 */
class GroupsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseAdmin(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $groups = Group::orderByRaw('display_order IS NULL')->orderBy('display_order')->orderBy('group_name')->get()
            ->reject(fn (Group $group) => in_array($group->identifier, ['default', 'no_profile'], true));

        return ext_view('panel.admin.groups', [
            'groups' => $groups,
            'members' => UserGroup::whereIn('group_id', $groups->modelKeys())->groupBy('group_id')->selectRaw('group_id, count(*) c')->pluck('c', 'group_id'),
        ]);
    }

    public function update(string $group)
    {
        $group = Group::findOrFail((int) $group);
        $description = trim(str_replace("\r\n", "\n", (string) Request::input('description')));
        if (mb_strlen($description) > 2000) {
            return $this->back($group, 'error', 'A description has 2000 characters at most.');
        }
        $group->update(['group_desc' => $description === '' ? null : $description]);
        // This worker's copy of the groups (the others refresh theirs within a minute).
        app('groups')->resetMemoized();
        $this->staffLog('group.description', $group->getKey(), "Changed the description of {$group->group_name}");

        return $this->back($group, 'success', "Saved {$group->group_name}'s description.");
    }

    private function back(Group $group, string $type, string $message)
    {
        return redirect(route('panel.admin.groups.index').'#group-'.$group->getKey())
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => $group->hasListing()
                ? ['url' => route('groups.show', ['group' => $group->getKey()]), 'text' => 'see its page']
                : null]);
    }
}
