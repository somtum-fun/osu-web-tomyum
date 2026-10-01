<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\User;
use Cache;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Http;

/**
 * TomYum: support tickets from email (staff panel → Tickets).
 *
 * Every minute (`tomyum:tickets-fetch`) the mailbox (Zoho, through NoCodeAPI) is read: an email to one
 * of the ticket addresses becomes a ticket in that address's category and gets the automatic reply,
 * or joins the ticket its subject names ("[Ticket #12]", from the same sender; a closed one reopens).
 * Staff reply from the panel, from the address the ticket came to. NoCodeAPI only gives the start of
 * each email (its summary, about 250 characters); the panel links to the full message in Zoho Mail.
 *
 * Who sees what: admins and developers every category; GMT accounts and appeals; the Support Team
 * (SPT) general support; the Tournament Committee (TC) tournaments.
 */
class TomYumTickets
{
    /** category => [label, the address's local part] */
    public const CATEGORIES = [
        'support' => ['General support', 'support'],
        'accounts' => ['Account support', 'accounts'],
        'appeal' => ['Appeal review', 'appeal-review'],
        'abuse' => ['Abuse report', 'abuse'],
        'privacy' => ['Privacy request', 'privacy'],
        'dmca' => ['DMCA request', 'dmca'],
        'tournaments' => ['Tournaments', 'tournaments'],
    ];

    /** Which groups handle which categories (admins and developers handle all of them). */
    private const GROUP_CATEGORIES = [
        'gmt' => ['accounts', 'appeal'],
        'spt' => ['support'],
        'tc' => ['tournaments'],
    ];

    private const SIGNATURE = 'production support';

    public static function domain(): string
    {
        return $GLOBALS['cfg']['tomyum']['tickets']['domain'];
    }

    public static function address(string $category): string
    {
        return static::CATEGORIES[$category][1].'@'.static::domain();
    }

    public static function label(string $category): string
    {
        return static::CATEGORIES[$category][0] ?? $category;
    }

    /** The categories this user may read and answer. */
    public static function categoriesFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }
        if ($user->isAdmin() || $user->isDev()) {
            return array_keys(static::CATEGORIES);
        }

        $categories = [];
        foreach (static::GROUP_CATEGORIES as $group => $groupCategories) {
            $groupModel = app('groups')->byIdentifier($group);
            if ($groupModel !== null && $user->isGroup($groupModel)) {
                array_push($categories, ...$groupCategories);
            }
        }

        return array_values(array_unique($categories));
    }

    public static function canAccess(?User $user, string $category): bool
    {
        return in_array($category, static::categoriesFor($user), true);
    }

    // ── Mail in ──────────────────────────────────────────────────────────────────────────────

    /** Reads the mailbox once; returns [new tickets, messages added to tickets]. */
    public static function fetch(): array
    {
        $base = static::apiBase();
        if ($base === null) {
            return [0, 0];
        }

        // Mail from before the system was turned on isn't turned into tickets.
        $since = (int) Cache::rememberForever('tomyum:tickets:since', fn () => Carbon::now()->getTimestampMs());

        $response = Http::timeout(30)->get("{$base}/listEmails", ['folderId' => static::inboxId($base), 'limit' => 50]);
        $emails = $response->json('data');
        if (!is_array($emails)) {
            throw new \RuntimeException('The mailbox could not be read: '.$response->body());
        }

        $created = 0;
        $added = 0;
        // Oldest first, so a reply lands after the email it answers.
        foreach (array_reverse($emails) as $email) {
            $messageId = (string) ($email['messageId'] ?? '');
            if ($messageId === '' || (int) ($email['receivedTime'] ?? 0) < $since) {
                continue;
            }
            if (DB::table('tomyum_ticket_messages')->where('mail_message_id', $messageId)->exists()) {
                continue;
            }

            $category = static::categoryOf(html_entity_decode(($email['toAddress'] ?? '').' '.($email['ccAddress'] ?? '')));
            $from = static::firstAddress(html_entity_decode($email['fromAddress'] ?? ''));
            if ($category === null || $from === null || static::isAutomated($from, $email['subject'] ?? '')) {
                continue;
            }

            $result = static::receive($category, $from, html_entity_decode($email['sender'] ?? ''), $email, $messageId);
            $result === 'created' ? $created++ : $added++;
        }

        return [$created, $added];
    }

    private static function receive(string $category, string $from, string $name, array $email, string $messageId): string
    {
        $subject = trim(html_entity_decode($email['subject'] ?? '')) ?: '(no subject)';
        $receivedAt = Carbon::createFromTimestampMs((int) ($email['receivedTime'] ?? Carbon::now()->getTimestampMs()));
        $body = trim(html_entity_decode($email['summary'] ?? ''));

        $ticket = null;
        if (preg_match('/\[Ticket #(\d+)\]/i', $subject, $match)) {
            $ticket = DB::table('tomyum_tickets')->where('id', (int) $match[1])->where('requester_email', $from)->first();
        }

        [$isNew, $ticketId] = DB::transaction(function () use ($ticket, $category, $from, $name, $subject, $receivedAt, $body, $messageId) {
            $isNew = $ticket === null;
            if ($isNew) {
                $ticketId = DB::table('tomyum_tickets')->insertGetId([
                    'category' => $category,
                    'status' => 'open',
                    'subject' => mb_substr($subject, 0, 255),
                    'requester_email' => $from,
                    'requester_name' => $name !== '' && $name !== $from ? mb_substr($name, 0, 255) : null,
                    'user_id' => User::where('user_email', $from)->value('user_id'),
                    'mailbox' => static::address($category),
                    'last_message_at' => $receivedAt,
                    'created_at' => $receivedAt,
                    'updated_at' => Carbon::now(),
                ]);
            } else {
                $ticketId = $ticket->id;
                $update = ['last_message_at' => $receivedAt, 'updated_at' => Carbon::now()];
                if ($ticket->status === 'closed') {
                    // A reply to a closed ticket opens it again.
                    $update += ['status' => 'open', 'closed_at' => null, 'closed_by' => null, 'close_reason' => null];
                }
                DB::table('tomyum_tickets')->where('id', $ticketId)->update($update);
            }

            DB::table('tomyum_ticket_messages')->insert([
                'ticket_id' => $ticketId,
                'direction' => 'in',
                'from_address' => $from,
                'subject' => mb_substr($subject, 0, 255),
                'body' => $body,
                'is_summary' => true,
                'mail_message_id' => $messageId,
                'created_at' => $receivedAt,
                'updated_at' => Carbon::now(),
            ]);

            return [$isNew, $ticketId];
        });

        // After the ticket is saved: a slow or failed send mustn't hold or undo it.
        if ($isNew) {
            static::sendAutoReply(DB::table('tomyum_tickets')->find($ticketId));
        }

        return $isNew ? 'created' : 'added';
    }

    // ── Mail out ─────────────────────────────────────────────────────────────────────────────

    public static function autoReplyText(object $ticket): string
    {
        $label = static::label($ticket->category);

        return <<<TEXT
            Hello,

            Thank you for contacting production. This is an automatic message to let you know your email has reached us and is now ticket #{$ticket->id} ({$label}).

            What happens next:
            - General questions and account help: a member of our team usually replies within a day or two.
            - Restrictions and appeals: these take longer to look into, usually 3 to 14 days. If your appeal is still on cooldown, we can't review it yet; please send it again once the cooldown is over.
            - Reports about a player or abusive chat: please also report them in the game, so staff have the full context.

            If you'd like to add anything, reply to this email and keep "[Ticket #{$ticket->id}]" in the subject. Otherwise there's no need to reply: we'll be in touch.

            Regards,

            production support
            TEXT;
    }

    /** A staff reply, in the usual greeting and signature. */
    public static function replyText(string $message): string
    {
        return "Hello,\n\n".trim($message)."\n\nRegards,\n\n".static::SIGNATURE;
    }

    public static function subjectFor(object $ticket): string
    {
        $subject = preg_replace('/\s*\[Ticket #\d+\]/i', '', $ticket->subject);

        return 'Re: '.preg_replace('/^(re:\s*)+/i', '', $subject)." [Ticket #{$ticket->id}]";
    }

    /** Sends a staff reply and records it; null when sent, otherwise why not. */
    public static function reply(object $ticket, User $staff, string $message): ?string
    {
        $text = static::replyText($message);
        if (!static::send($ticket, $text)) {
            return "The email couldn't be sent. Please try again.";
        }

        DB::transaction(function () use ($ticket, $staff, $text) {
            DB::table('tomyum_ticket_messages')->insert([
                'ticket_id' => $ticket->id,
                'direction' => 'out',
                'from_address' => $ticket->mailbox,
                'subject' => mb_substr(static::subjectFor($ticket), 0, 255),
                'body' => $text,
                'author_id' => $staff->getKey(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            DB::table('tomyum_tickets')->where('id', $ticket->id)->update(['last_message_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
            static::addHandler($ticket->id, $staff->getKey());
        });

        return null;
    }

    public static function note(object $ticket, User $staff, string $text): void
    {
        DB::table('tomyum_ticket_messages')->insert([
            'ticket_id' => $ticket->id,
            'direction' => 'note',
            'body' => $text,
            'author_id' => $staff->getKey(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private static function sendAutoReply(object $ticket): void
    {
        $text = static::autoReplyText($ticket);
        if (static::send($ticket, $text)) {
            DB::table('tomyum_ticket_messages')->insert([
                'ticket_id' => $ticket->id,
                'direction' => 'auto',
                'from_address' => $ticket->mailbox,
                'subject' => mb_substr(static::subjectFor($ticket), 0, 255),
                'body' => $text,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * Sends from the ticket's address; if Zoho refuses it (not set up as an alias), from the server's
     * main address instead, so the requester still gets an answer.
     */
    private static function send(object $ticket, string $text): bool
    {
        $base = static::apiBase();
        if ($base === null) {
            return false;
        }

        $fallback = $GLOBALS['cfg']['mail']['from']['address'];
        foreach (array_unique([$ticket->mailbox, $fallback]) as $from) {
            try {
                $response = Http::timeout(30)
                    ->withQueryParameters([
                        'fromAddress' => $from,
                        'toAddress' => $ticket->requester_email,
                        'subject' => static::subjectFor($ticket),
                        'content' => $text,
                        'mailFormat' => 'plaintext',
                    ])
                    ->post("{$base}/sendEmail", (object) []);
                if ($response->successful() && ($response->json('status.code') ?? 200) === 200) {
                    return true;
                }
                \Log::warning("Ticket #{$ticket->id}: sending from {$from} failed", ['response' => $response->body()]);
            } catch (\Throwable $e) {
                log_error($e);
            }
        }

        return false;
    }

    // ── Handling ─────────────────────────────────────────────────────────────────────────────

    public static function addHandler(int $ticketId, int $userId): void
    {
        DB::table('tomyum_ticket_handlers')->insertOrIgnore(['ticket_id' => $ticketId, 'user_id' => $userId, 'added_at' => Carbon::now()]);
    }

    public static function removeHandler(int $ticketId, int $userId): void
    {
        DB::table('tomyum_ticket_handlers')->where('ticket_id', $ticketId)->where('user_id', $userId)->delete();
    }

    public static function close(object $ticket, User $staff, string $reason): void
    {
        DB::table('tomyum_tickets')->where('id', $ticket->id)->update([
            'status' => 'closed',
            'closed_at' => Carbon::now(),
            'closed_by' => $staff->getKey(),
            'close_reason' => mb_substr($reason, 0, 500),
            'updated_at' => Carbon::now(),
        ]);
        static::addHandler($ticket->id, $staff->getKey());
    }

    public static function reopen(object $ticket): void
    {
        DB::table('tomyum_tickets')->where('id', $ticket->id)->update([
            'status' => 'open', 'closed_at' => null, 'closed_by' => null, 'close_reason' => null, 'updated_at' => Carbon::now(),
        ]);
    }

    /** The message in Zoho Mail's web app (the whole email, which the panel only has the start of). */
    public static function webMailUrl(string $messageId): string
    {
        return 'https://mail.zoho.com/zm/#mail/folder/inbox/p/'.rawurlencode($messageId);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    /** The NoCodeAPI Zoho Mail endpoint (MAIL_DRIVER=nocodeapi's URL without /sendEmail). */
    private static function apiBase(): ?string
    {
        $url = $GLOBALS['cfg']['tomyum']['nocodeapi_mail_url'] ?? null;

        return $url === null ? null : preg_replace('#/sendEmail/?$#', '', rtrim($url, '/'));
    }

    private static function inboxId(string $base): string
    {
        return Cache::remember('tomyum:tickets:inbox-id', 86400, function () use ($base) {
            foreach (Http::timeout(30)->get("{$base}/listFolders")->json('data') ?? [] as $folder) {
                if (($folder['folderType'] ?? null) === 'Inbox' && ($folder['folderName'] ?? null) === 'Inbox') {
                    return (string) $folder['folderId'];
                }
            }

            throw new \RuntimeException('The mailbox has no Inbox folder.');
        });
    }

    /** The ticket category of the first ticket address among these recipients, or null. */
    private static function categoryOf(string $recipients): ?string
    {
        preg_match_all('/[\w.+-]+@[\w.-]+/', strtolower($recipients), $matches);
        foreach ($matches[0] as $address) {
            foreach (array_keys(static::CATEGORIES) as $category) {
                if ($address === static::address($category)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private static function firstAddress(string $text): ?string
    {
        return preg_match('/[\w.+-]+@[\w.-]+\.\w+/', strtolower($text), $match) ? $match[0] : null;
    }

    /** Mail that mustn't open tickets: ours, bounces, and other automatic replies. */
    private static function isAutomated(string $from, string $subject): bool
    {
        return str_ends_with($from, '@'.static::domain())
            || preg_match('/^(mailer-daemon|postmaster|no-?reply|do-?not-?reply)@/', $from) === 1
            || preg_match('/^(auto(matic)?[ -]?reply|out of office|undeliver|delivery status)/i', html_entity_decode($subject)) === 1;
    }
}
