<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: bot accounts made from the admin panel (they're in the `bot` group) and who owns each.
 * The owner manages the bot (avatar, banner, me! page, name, IRC key) and gets its website
 * verification codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_bot_accounts', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->unsignedInteger('owner_id')->nullable()->index();
            $table->unsignedInteger('created_by')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_bot_accounts');
    }
};
