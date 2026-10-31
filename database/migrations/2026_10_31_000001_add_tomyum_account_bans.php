<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: banned accounts: restricted, no logins anywhere, and their hardware banned with them (the
 * game server matches logins against the hashes it recorded). TomYum reads banned_at at every login,
 * so run this before deploying a TomYum that knows about bans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tomyum_account_flags', function (Blueprint $table) {
            $table->dateTime('banned_at')->nullable()->after('lock_reason');
            $table->unsignedInteger('banned_by')->nullable()->after('banned_at');
            $table->string('ban_reason', 500)->nullable()->after('banned_by');
        });
    }

    public function down(): void
    {
        Schema::table('tomyum_account_flags', function (Blueprint $table) {
            $table->dropColumn(['banned_at', 'banned_by', 'ban_reason']);
        });
    }
};
