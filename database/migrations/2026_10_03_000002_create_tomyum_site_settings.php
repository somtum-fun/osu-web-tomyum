<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: website settings staff change in the admin panel (the development banner first), as
 * name → value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_site_settings', function (Blueprint $table) {
            $table->string('name', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_site_settings');
    }
};
