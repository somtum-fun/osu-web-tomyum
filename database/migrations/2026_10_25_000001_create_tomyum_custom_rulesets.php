<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: custom lazer rulesets (TomYumCustomRulesets). Staff enable rulesets from rulesets.info in the admin panel;
 * enabling one pins its release: the DLL's URL and SHA-256, which TumThai downloads and which scores must match. The id
 * is the ruleset id scores are stored with (10 and up; sentakki, tau, rush, hishigata and soyokaze keep GooGuTeam's).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tomyum_custom_rulesets')) {
            return;
        }

        Schema::create('tomyum_custom_rulesets', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 100);
            // The DLL's assembly name (osu.Game.Rulesets.Sentakki): how TumThai knows a loaded ruleset is this one.
            $table->string('assembly', 128)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('version', 64)->nullable();
            $table->string('download_url', 500);
            $table->char('sha256', 64);
            $table->unsignedInteger('size');
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedInteger('pinned_by')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_custom_rulesets');
    }
};
