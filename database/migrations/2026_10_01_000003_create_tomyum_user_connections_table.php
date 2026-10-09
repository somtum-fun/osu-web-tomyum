<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: accounts players link from their settings (Discord, their osu! account on osu.ppy.sh), as
 * bancho.py-style user_connections. A Discord link is also written to TomYum's discord_links
 * (the Discord bot's). `data` keeps what the osu! check found (restriction seen, recent silences).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_user_connections', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            // discord, osu_bancho
            $table->string('kind', 16);
            $table->string('external_id', 32);
            $table->string('display_name', 100)->nullable();
            $table->json('data')->nullable();
            $table->dateTime('connected_at');
            $table->dateTime('checked_at')->nullable();

            $table->primary(['user_id', 'kind']);
            $table->unique(['kind', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_user_connections');
    }
};
