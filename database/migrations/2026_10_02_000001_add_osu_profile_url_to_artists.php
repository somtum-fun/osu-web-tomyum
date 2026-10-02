<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: a featured artist's osu! profile (osu.ppy.sh/users/…). user_id is a profile on this
 * server, and osu!'s user ids are other people here, so osu!'s is kept as a link: the artist page
 * shows the profile here when there's one, and osu!'s otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->string('osu_profile_url')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn('osu_profile_url');
        });
    }
};
