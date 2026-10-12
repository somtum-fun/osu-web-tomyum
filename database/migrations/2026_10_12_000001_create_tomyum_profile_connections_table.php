<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: the linked accounts (osu!, Discord, Last.fm) a player shows on their profile, picked in
 * account settings. A row means shown; nothing is shown until the player says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_profile_connections', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            // osu_bancho, discord, lastfm
            $table->string('kind', 16);

            $table->primary(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_profile_connections');
    }
};
