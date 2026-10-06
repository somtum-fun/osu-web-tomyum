<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: when a restricted player may appeal (App\Libraries\TomYumAppeals), set by the GMT
 * restrict form from the offense (Restrictions & Appeals page: cheating 3 months, 12 for a second
 * time, multi-accounting and account sharing 2) and moved 3 months on by a denied appeal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_appeal_cooldowns', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            // cheating, multi, sharing, other
            $table->string('offense', 16);
            // How many times they were restricted for cheating (the second time waits 12 months).
            $table->unsignedTinyInteger('cheating_offenses')->default(0);
            $table->unsignedTinyInteger('denied_appeals')->default(0);
            $table->dateTime('restricted_at');
            $table->dateTime('appeal_after');
            $table->unsignedInteger('set_by')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_appeal_cooldowns');
    }
};
