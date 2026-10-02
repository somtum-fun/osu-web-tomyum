<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use App\Models\Group;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TomYum: the Project Loved forum (#120 under Beatmaps, osu!'s id), where the Project Loved site
 * (loved.<domain>) posts each round's topics and Yes/No polls. Its topics are started by the
 * Project Loved team and the staff; everyone replies and votes. Only made when missing (TomYumSetup
 * makes it on a new server). Also gives the Project Loved group modes, as on osu! (each member
 * captains the modes they have there); its members so far get all four.
 */
return new class extends Migration
{
    private const FORUM_ID = 120;

    public function up(): void
    {
        $loved = Group::where('identifier', 'loved')->first();
        if ($loved !== null && !$loved->has_playmodes) {
            DB::table('phpbb_groups')->where('group_id', $loved->getKey())->update(['has_playmodes' => true]);
            DB::table('phpbb_user_group')->where('group_id', $loved->getKey())->whereNull('playmodes')
                ->update(['playmodes' => json_encode(['osu', 'taiko', 'fruits', 'mania'])]);
            app('groups')->resetMemoized();
        }

        if (DB::table('phpbb_forums')->where('forum_id', static::FORUM_ID)->exists()) {
            return;
        }

        $beatmaps = DB::table('phpbb_forums')->where('forum_id', 40)->first();
        $after = DB::table('phpbb_forums')->where('parent_id', 40)->max('left_id') ?? ($beatmaps->left_id ?? 0);
        DB::table('phpbb_forums')->insert([
            'forum_id' => static::FORUM_ID,
            'parent_id' => $beatmaps === null ? 0 : 40,
            'left_id' => $after + 1,
            'right_id' => $after + 1,
            'forum_name' => 'Project Loved',
            'forum_desc' => 'Vote on the maps nominated for Loved each round.',
            'forum_parents' => $beatmaps === null ? '' : serialize([40 => [$beatmaps->forum_name, 0]]),
            'forum_rules' => '',
            'forum_type' => 1,
            'enable_indexing' => 1,
        ]);

        $options = DB::table('phpbb_acl_options')->whereIn('auth_option', ['f_post', 'f_reply', 'f_postcount'])->pluck('auth_option_id', 'auth_option');
        $grant = function (?int $groupId, array $names) use ($options) {
            if ($groupId === null) {
                return;
            }
            foreach ($names as $name) {
                if (isset($options[$name])) {
                    DB::table('phpbb_acl_groups')->updateOrInsert([
                        'group_id' => $groupId,
                        'forum_id' => static::FORUM_ID,
                        'auth_option_id' => $options[$name],
                        'auth_role_id' => 0,
                    ], ['auth_setting' => 1]);
                }
            }
        };
        foreach (['admin', 'dev', 'gmt', 'loved'] as $identifier) {
            $grant(Group::where('identifier', $identifier)->value('group_id'), ['f_post', 'f_reply', 'f_postcount']);
        }
        $grant(Group::where('identifier', 'default')->value('group_id'), ['f_reply', 'f_postcount']);
    }

    public function down(): void
    {
        DB::table('phpbb_acl_groups')->where('forum_id', static::FORUM_ID)->delete();
        DB::table('phpbb_forums')->where('forum_id', static::FORUM_ID)->delete();
    }
};
