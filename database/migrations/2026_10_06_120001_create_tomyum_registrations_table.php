<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: every account made (from lazer or stable) and every sign-up the registration rules
 * refused (App\Libraries\TomYumRegistrationGuard), for the "1 account per network a day" rule and
 * the GMT panel's sign-ups page. Rows are kept 90 days (tomyum:registrations-prune).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_registrations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ip', 45);
            // The address an IPv4 sign-up came from; for IPv6, its /64 (one home or phone).
            $table->string('ip_prefix', 64);
            $table->unsignedInteger('asn')->nullable();
            $table->char('country', 2)->nullable();
            // lazer, stable, web
            $table->string('source', 8);
            // created, blocked_ban, blocked_ip_limit, blocked_tor, blocked_hosting, blocked_proxy, blocked_invalid_ip
            $table->string('result', 32);
            // What the player was told (blocked), or a note such as "proxy check unavailable".
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('username', 32)->nullable();
            $table->dateTime('created_at')->index();

            $table->index(['ip_prefix', 'result', 'created_at']);
            $table->index(['result', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_registrations');
    }
};
