<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: when each osu! beatmap set was last read again from bancho (TomYumBeatmapSync), so the
 * scheduled re-sync goes round them: sets with missing details first, then sets whose status on
 * osu! may still change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_beatmapset_syncs', function (Blueprint $table) {
            $table->unsignedInteger('beatmapset_id')->primary();
            $table->dateTime('synced_at')->index();
            // synced, not_found, failed
            $table->string('result', 16);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_beatmapset_syncs');
    }
};
