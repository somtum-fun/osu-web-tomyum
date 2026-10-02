<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: Project Loved requests (Community → Project Loved → Requests). Players ask for a map in a
 * mode, others support the request, and the Loved team picks it into a round or declines it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_loved_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('beatmapset_id')->index();
            $table->unsignedTinyInteger('ruleset_id');
            // Who asked first, and why.
            $table->unsignedInteger('user_id')->index();
            $table->text('reason');
            // open, picked (into a round), declined
            $table->string('status', 8)->default('open')->index();
            $table->unsignedInteger('supporters_count')->default(1);
            $table->unsignedInteger('nomination_id')->nullable();
            // The team member who picked or declined it, and why it was declined.
            $table->unsignedInteger('handled_by')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('tomyum_loved_request_supporters', function (Blueprint $table) {
            $table->unsignedInteger('request_id');
            $table->unsignedInteger('user_id')->index();
            $table->timestamp('created_at')->nullable();

            $table->primary(['request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_loved_request_supporters');
        Schema::dropIfExists('tomyum_loved_requests');
    }
};
