<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: Project Loved (App\Libraries\TomYumLoved). The Loved team puts maps in a round, each
 * nomination gets a forum topic with a Yes/No poll, and closing the round loves the ones that passed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_loved_rounds', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            // picking (the team adds maps; not public), voting (polls open), closed (results in)
            $table->string('state', 8)->default('picking')->index();
            // Share of yes votes a map needs, in percent, and how many votes at least.
            $table->unsignedTinyInteger('threshold')->default(70);
            $table->unsignedSmallInteger('min_votes')->default(10);
            $table->dateTime('voting_ends_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tomyum_loved_nominations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('round_id')->index();
            $table->unsignedInteger('beatmapset_id')->index();
            // 0 osu!, 1 taiko, 2 catch, 3 mania: a set can be up in several modes, each its own poll.
            $table->unsignedTinyInteger('ruleset_id');
            // The captain who picked it, and why it deserves Loved (shown on the round and in the topic).
            $table->unsignedInteger('captain_id');
            $table->text('description')->nullable();
            // The poll's forum topic, once voting opened.
            $table->unsignedInteger('topic_id')->nullable();
            // Filled in when the round closes.
            $table->unsignedInteger('result_yes')->nullable();
            $table->unsignedInteger('result_no')->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamps();

            $table->unique(['round_id', 'beatmapset_id', 'ruleset_id'], 'tomyum_loved_nominations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_loved_nominations');
        Schema::dropIfExists('tomyum_loved_rounds');
    }
};
