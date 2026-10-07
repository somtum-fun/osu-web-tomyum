<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: how many players were online, every 5 minutes (tomyum:online-sample), by client, for the
 * admin panel's chart. Kept 30 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_online_samples', function (Blueprint $table) {
            $table->timestamp('sampled_at')->primary();
            $table->unsignedSmallInteger('stable')->default(0);
            $table->unsignedSmallInteger('lazer')->default(0);
            $table->unsignedSmallInteger('website')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_online_samples');
    }
};
