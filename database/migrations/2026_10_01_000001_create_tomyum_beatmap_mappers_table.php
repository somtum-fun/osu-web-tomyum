<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: who made a set and each of its difficulties on osu! (from osu!'s API). Imported sets keep
 * no mapper accounts here, so they're kept by osu! user id and name and shown with their osu!
 * profiles and avatars. beatmap_id 0 is the set's creator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_beatmap_mappers', function (Blueprint $table) {
            $table->unsignedMediumInteger('beatmapset_id');
            $table->unsignedInteger('beatmap_id');
            $table->unsignedInteger('osu_user_id');
            $table->string('username', 32);
            $table->primary(['beatmapset_id', 'beatmap_id', 'osu_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_beatmap_mappers');
    }
};
