<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: the changelog's streams (lazer, Stable, Legacy clients, Server, Website, Discord,
 * Wiki: App\Libraries\TomYumChangelog), and who wrote a change written on the changelog panel: a
 * player here (tomyum_user_id) or just a name (tomyum_author), for changes without a GitHub account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('changelog_entries', function (Blueprint $table) {
            $table->unsignedInteger('tomyum_user_id')->nullable();
            $table->string('tomyum_author', 100)->nullable();
        });

        $rows = [];
        foreach (\App\Libraries\TomYumChangelog::STREAMS as $name => [$id, $pretty]) {
            $rows[] = ['stream_id' => $id, 'name' => $name, 'pretty_name' => $pretty, 'default_allow_bancho' => false];
        }
        DB::connection('mysql-updates')->table('streams')->upsert($rows, ['stream_id'], ['name', 'pretty_name']);
    }

    public function down(): void
    {
        Schema::table('changelog_entries', function (Blueprint $table) {
            $table->dropColumn(['tomyum_user_id', 'tomyum_author']);
        });
    }
};
