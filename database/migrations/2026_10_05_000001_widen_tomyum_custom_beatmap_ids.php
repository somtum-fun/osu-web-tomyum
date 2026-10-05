<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TomYum: custom beatmaps' ids start at 10,000,000, past signed MEDIUMINT's 8,388,607, so lazer
 * couldn't get a score token for them ("failed creating score token"), nor could their points of
 * failure or medals be kept. These beatmap_id columns become INT UNSIGNED like the rest.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'solo_score_tokens' => 'NOT NULL',
        'osu_beatmap_failtimes' => 'NOT NULL',
        'osu_user_achievements' => 'NULL DEFAULT NULL',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $nullability) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `beatmap_id` INT UNSIGNED {$nullability}");
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $nullability) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `beatmap_id` MEDIUMINT {$nullability}");
        }
    }
};
