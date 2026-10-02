<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use App\Models\Group;
use App\Models\Rank;
use Illuminate\Database\Migrations\Migration;

/**
 * TomYum: the Featured Artist group (featured_artist), like osu!'s: a cyan FA badge for the accounts
 * of featured artists, given and taken with an artist's profile in the artist editor
 * (Panel\ArtistsController), with its "Featured Artist" title (given with the group, like the
 * Tournament Committee's). Listed before the chat bots. Only made when missing.
 */
return new class extends Migration
{
    private const ORDER = 10;

    public function up(): void
    {
        if (Rank::where('rank_special', 1)->where('rank_title', 'Featured Artist')->doesntExist()) {
            Rank::create([
                'rank_image' => '',
                'rank_min' => 0,
                'rank_special' => 1,
                'rank_title' => 'Featured Artist',
            ]);
        }

        if (Group::where('identifier', 'featured_artist')->exists()) {
            return;
        }

        Group::whereNotNull('display_order')
            ->where('display_order', '>=', static::ORDER)
            ->increment('display_order');

        Group::create([
            'colour' => '00FFFF',
            'display_order' => static::ORDER,
            'group_colour' => '00FFFF',
            'group_desc' => '',
            'group_name' => 'Featured Artist',
            'group_type' => 1,
            'has_playmodes' => false,
            'identifier' => 'featured_artist',
            'short_name' => 'FA',
        ]);
    }

    public function down(): void
    {
        $group = Group::where('identifier', 'featured_artist')->first();
        if ($group !== null) {
            $group->delete();
            Group::whereNotNull('display_order')
                ->where('display_order', '>', static::ORDER)
                ->decrement('display_order');
        }
    }
};
