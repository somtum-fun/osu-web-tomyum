<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: where a player report stands in the GMT panel's queue (App\Libraries\TomYumReports): taken
 * by a staff member, or resolved with a note. A report without a row is open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_report_states', function (Blueprint $table) {
            $table->unsignedInteger('report_id')->primary();
            $table->string('status', 16); // taken, resolved
            $table->unsignedInteger('handler_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_report_states');
    }
};
