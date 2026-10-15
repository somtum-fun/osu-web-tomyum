<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: tickets keep the whole email: the full text (a MEDIUMTEXT, no more 15,000-character cut),
 * the earlier messages a reply quotes (shown folded), its HTML (shown cleaned, in a sandbox), its
 * attachments and pictures, and the Message-IDs that thread replies (In-Reply-To / References).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tomyum_ticket_messages', function (Blueprint $table) {
            $table->mediumText('body')->change();
            // The quoted earlier messages under a reply's own text.
            $table->mediumText('quoted')->nullable()->after('body');
            // The email's HTML part as it came (cleaned when it's shown).
            $table->mediumText('html')->nullable()->after('quoted');
            // The email's own Message-ID (without <>), ours for staff and automatic replies.
            $table->string('rfc_message_id', 255)->nullable()->after('mail_message_id')->index();
        });

        Schema::create('tomyum_ticket_attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('message_id')->index();
            $table->string('filename', 255);
            $table->string('mime', 127);
            $table->unsignedInteger('size');
            // An inline part's Content-ID (an HTML email's <img src="cid:…">).
            $table->string('content_id', 255)->nullable();
            $table->boolean('inline')->default(false);
            $table->char('sha256', 64);
            // Under the tickets disk (storage/app/tickets).
            $table->string('path', 255);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_ticket_attachments');
        Schema::table('tomyum_ticket_messages', function (Blueprint $table) {
            $table->dropColumn(['quoted', 'html', 'rfc_message_id']);
        });
    }
};
