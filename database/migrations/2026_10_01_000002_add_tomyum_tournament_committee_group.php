<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use App\Models\Group;
use App\Models\Rank;
use Illuminate\Database\Migrations\Migration;

/**
 * TomYum: the Tournament Committee (tc): a listed group with a gold TC badge, right after the
 * GMT (the groups from the NAT on move down one), and its "Tournament Committee" title, which
 * members get when they're added (Panel\AdminController). Only made when missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Group::where('identifier', 'tc')->doesntExist()) {
            Group::whereNotNull('display_order')
                ->where('display_order', '>=', 3)
                ->increment('display_order');

            Group::create([
                'colour' => 'FFC266',
                'display_order' => 3,
                'group_colour' => 'FFC266',
                'group_desc' => '',
                'group_name' => 'Tournament Committee',
                'group_type' => 1,
                'has_playmodes' => false,
                'identifier' => 'tc',
                'short_name' => 'TC',
            ]);
        }

        if (Rank::where('rank_special', 1)->where('rank_title', 'Tournament Committee')->doesntExist()) {
            Rank::create([
                'rank_image' => '',
                'rank_min' => 0,
                'rank_special' => 1,
                'rank_title' => 'Tournament Committee',
            ]);
        }
    }

    public function down(): void
    {
        $group = Group::where('identifier', 'tc')->first();
        if ($group !== null) {
            $group->delete();
            Group::whereNotNull('display_order')
                ->where('display_order', '>', 3)
                ->decrement('display_order');
        }
    }
};
