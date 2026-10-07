<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: a second title (a phpbb_ranks row, like user_rank), shown after the first on the profile
 * as osu! does ("Former osu! Champion: 2023 / Mapping Architect"). 0 is none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phpbb_users', function (Blueprint $table) {
            $table->mediumInteger('tomyum_user_rank_2')->unsigned()->default(0)->after('user_rank');
        });
    }

    public function down(): void
    {
        Schema::table('phpbb_users', function (Blueprint $table) {
            $table->dropColumn('tomyum_user_rank_2');
        });
    }
};
