<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: tournaments run from the staff panel (TC and admins): who may register (countries, no
 * restriction or silence in the last N days, account age, Discord, a player cap), hidden drafts,
 * and the rows the tournament banners page makes (banner-only, kept off the tournaments list).
 * Registrations keep the player's country when they signed up and the check made when
 * registration closed. A player can only be registered once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->json('countries')->nullable();
            $table->unsignedSmallInteger('restriction_days')->nullable();
            $table->unsignedSmallInteger('silence_days')->nullable();
            $table->unsignedSmallInteger('account_age_days')->nullable();
            $table->unsignedInteger('max_registrations')->nullable();
            $table->boolean('requires_discord')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_banner_only')->default(false);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('rechecked_at')->nullable();
        });

        // The banners page's tournaments so far are only there for their banners.
        if (Schema::hasTable('tournament_banners')) {
            DB::table('tournaments')
                ->whereIn('tournament_id', DB::table('tournament_banners')->select('tournament_id'))
                ->where('description', '')
                ->update(['is_banner_only' => true]);
        }

        // One row per player and tournament (keeps the oldest).
        DB::statement('DELETE r1 FROM tournament_registrations r1
            JOIN tournament_registrations r2
                ON r1.tournament_id = r2.tournament_id AND r1.user_id = r2.user_id AND r1.registration_id > r2.registration_id');

        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->char('country_acronym', 2)->nullable();
            $table->json('ineligible_reasons')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->unique(['tournament_id', 'user_id'], 'tournament_registrations_tournament_user');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropUnique('tournament_registrations_tournament_user');
            $table->dropColumn(['country_acronym', 'ineligible_reasons', 'checked_at']);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'countries',
                'restriction_days',
                'silence_days',
                'account_age_days',
                'max_registrations',
                'requires_discord',
                'is_hidden',
                'is_banner_only',
                'created_by',
                'rechecked_at',
            ]);
        });
    }
};
