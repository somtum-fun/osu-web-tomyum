<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: who nominated a set on osu! (from osu!'s API). They have no account on this site, so
 * they're kept by osu! user id and name and shown with links to their osu! profiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_beatmapset_nominators', function (Blueprint $table) {
            $table->unsignedMediumInteger('beatmapset_id');
            $table->unsignedInteger('osu_user_id');
            $table->string('username', 32);
            $table->json('rulesets')->nullable();
            $table->primary(['beatmapset_id', 'osu_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_beatmapset_nominators');
    }
};
