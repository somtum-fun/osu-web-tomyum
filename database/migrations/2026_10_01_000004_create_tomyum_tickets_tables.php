<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TomYum: the support ticket system (staff panel → Tickets). Every email to one of the server's
 * addresses (support@, accounts@, appeal-review@, …) becomes a ticket, or joins the ticket its subject
 * names ("[Ticket #12]"); staff reply from the panel. Also the Support Team (SPT) and Tournament
 * Committee (TC) groups, whose members handle some of the categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomyum_tickets', function (Blueprint $table) {
            $table->increments('id');
            // privacy, accounts, support, dmca, abuse, appeal, tournaments
            $table->string('category', 16)->index();
            // open, closed
            $table->string('status', 8)->default('open')->index();
            $table->string('subject', 255);
            $table->string('requester_email', 255)->index();
            $table->string('requester_name', 255)->nullable();
            // The account with the requester's email, if any.
            $table->unsignedInteger('user_id')->nullable()->index();
            // The address it was sent to (replies go from it).
            $table->string('mailbox', 255);
            $table->dateTime('last_message_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedInteger('closed_by')->nullable();
            $table->string('close_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('tomyum_ticket_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ticket_id')->index();
            // in (from the requester), out (a staff reply), auto (the automatic reply), note (staff only)
            $table->string('direction', 8);
            $table->string('from_address', 255)->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('body');
            // Only the start of an email when the mailbox gives a summary, not the whole message.
            $table->boolean('is_summary')->default(false);
            $table->string('mail_message_id', 64)->nullable()->unique();
            $table->unsignedInteger('author_id')->nullable();
            $table->timestamps();
        });

        Schema::create('tomyum_ticket_handlers', function (Blueprint $table) {
            $table->unsignedInteger('ticket_id');
            $table->unsignedInteger('user_id');
            $table->dateTime('added_at');
            $table->primary(['ticket_id', 'user_id']);
        });

        foreach ([
            ['spt', 'Support Team', 'SPT', 'EB8C47', 7],
            ['tc', 'Tournament Committee', 'TC', '4FB8E0', 7],
        ] as [$identifier, $name, $short, $colour, $order]) {
            if (!DB::table('phpbb_groups')->where('identifier', $identifier)->exists()) {
                DB::table('phpbb_groups')->insert([
                    'identifier' => $identifier,
                    'group_name' => $name,
                    'short_name' => $short,
                    'colour' => $colour,
                    'display_order' => $order,
                    'group_type' => 1,
                    'group_legend' => 1,
                    'has_playmodes' => false,
                    'group_desc' => '',
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tomyum_ticket_handlers');
        Schema::dropIfExists('tomyum_ticket_messages');
        Schema::dropIfExists('tomyum_tickets');
        DB::table('phpbb_groups')->whereIn('identifier', ['spt', 'tc'])->delete();
    }
};
