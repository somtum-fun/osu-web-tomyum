<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: Project Loved without rounds. Players ask for a map in a mode and review each other's
 * requests (for or against, with why); the Loved team loves, rejects or removes a request, or
 * blacklists the map. Supporters become "for" reviews, and the rounds' tables go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_loved_request_reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('request_id')->index();
            $table->unsignedInteger('user_id')->index();
            // 1 for, -1 against
            $table->tinyInteger('score');
            $table->text('comment');
            $table->timestamps();

            $table->unique(['request_id', 'user_id']);
        });

        Schema::create('tomyum_loved_blacklist', function (Blueprint $table) {
            $table->unsignedInteger('beatmapset_id')->primary();
            $table->text('reason');
            $table->unsignedInteger('user_id');
            $table->timestamp('created_at')->nullable();
        });

        // Supporters other than the asker count as "for".
        if (Schema::hasTable('tomyum_loved_request_supporters')) {
            DB::statement("
                INSERT IGNORE INTO tomyum_loved_request_reviews (request_id, user_id, score, comment, created_at, updated_at)
                SELECT s.request_id, s.user_id, 1, '', s.created_at, s.created_at
                FROM tomyum_loved_request_supporters s
                JOIN tomyum_loved_requests r ON r.id = s.request_id
                WHERE s.user_id <> r.user_id
            ");
        }

        Schema::table('tomyum_loved_requests', function (Blueprint $table) {
            $table->string('status', 10)->default('open')->change();
            // The reviews' sum, the asker's "for" included: the list's order.
            $table->integer('score')->default(1)->after('status');
            $table->renameColumn('decline_reason', 'decision_reason');
            $table->dateTime('decided_at')->nullable()->after('handled_by');
        });
        Schema::table('tomyum_loved_requests', function (Blueprint $table) {
            $table->text('decision_reason')->nullable()->change();
            $table->dropColumn(['supporters_count', 'nomination_id']);
        });

        // open, loved, rejected, removed (picked into a round: loved by it, or still to come)
        DB::table('tomyum_loved_requests')->where('status', 'declined')->update(['status' => 'rejected']);
        DB::table('tomyum_loved_requests')->where('status', 'picked')->update(['status' => 'open']);
        DB::statement('
            UPDATE tomyum_loved_requests r
            SET r.score = 1 + (SELECT COALESCE(SUM(v.score), 0) FROM tomyum_loved_request_reviews v WHERE v.request_id = r.id)
        ');
        // One request per map and mode: the newest stays.
        DB::statement('
            DELETE r FROM tomyum_loved_requests r
            JOIN tomyum_loved_requests newer
                ON newer.beatmapset_id = r.beatmapset_id AND newer.ruleset_id = r.ruleset_id AND newer.id > r.id
        ');
        Schema::table('tomyum_loved_requests', function (Blueprint $table) {
            $table->unique(['beatmapset_id', 'ruleset_id']);
        });

        Schema::dropIfExists('tomyum_loved_request_supporters');
        Schema::dropIfExists('tomyum_loved_nominations');
        Schema::dropIfExists('tomyum_loved_rounds');
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_loved_blacklist');
        Schema::dropIfExists('tomyum_loved_request_reviews');
    }
};
