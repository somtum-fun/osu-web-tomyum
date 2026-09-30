<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: staff flags on an account (locked: no logins anywhere; whitelisted: anti-cheat leaves it
 * alone), and every login (website, lazer, stable) for the admin panel. TomYum reads and writes both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_account_flags', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedInteger('locked_by')->nullable();
            $table->string('lock_reason', 500)->nullable();
            $table->dateTime('whitelisted_at')->nullable();
            $table->unsignedInteger('whitelisted_by')->nullable();
            $table->string('whitelist_reason', 500)->nullable();
        });

        Schema::create('tomyum_login_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            // stable, lazer, web
            $table->string('client', 16);
            // ok, held (device to verify), locked, bad_password
            $table->string('result', 16);
            $table->string('ip', 45)->nullable();
            $table->char('country', 2)->nullable();
            // The browser's user agent, or the game's version.
            $table->string('agent', 255)->nullable();
            $table->dateTime('created_at');
            $table->index(['user_id', 'id']);
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_login_history');
        Schema::dropIfExists('tomyum_account_flags');
    }
};
