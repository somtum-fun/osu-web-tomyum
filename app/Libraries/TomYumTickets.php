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
use Illuminate\Support\Facades\Mail;

/**
 * TomYum: support tickets from email (staff panel → Tickets).
 *
 * Every minute (`tomyum:tickets-fetch`) the mailbox is read: an email to one of the ticket addresses
 * becomes a ticket in that address's category and gets the automatic reply, or joins the ticket its
 * subject names ("[Ticket #12]", from the same sender; a closed one reopens). Staff reply from the
 * panel, from the address the ticket came to.
 *
 * The mailbox is either any IMAP one (tomyum.tickets.imap: Spacemail, Zoho IMAP, …; replies then go
 * by SMTP, the site's MAIL_* server), with whole emails, or Zoho through NoCodeAPI, which only gives
 * the start of each email (its summary, about 250 characters; the panel links to it in Zoho Mail).
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

    /** The signature under ticket mail: "<server name> support" (tomyum.server_name). */
    public static function signature(): string
    {
        return $GLOBALS['cfg']['tomyum']['server_name'].' support';
    }

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
        $created = 0;
        $added = 0;
        // Each email, oldest first (so a reply lands after the email it answers), as
        // [id, to (every recipient), from, name, subject, body, is_summary, received_at, automated].
        $handle = function (array $email) use (&$created, &$added) {
            if (DB::table('tomyum_ticket_messages')->where('mail_message_id', $email['id'])->exists()) {
                return;
            }

            $category = static::categoryOf($email['to']);
            $from = static::firstAddress($email['from']);
            if ($category === null || $from === null || $email['automated'] || static::isAutomated($from, $email['subject'])) {
                return;
            }

            static::receive($category, $from, $email) === 'created' ? $created++ : $added++;
        };

        if (static::imapConfig() !== null) {
            static::fetchImap($handle);
        } elseif (static::apiBase() !== null) {
            static::fetchZoho($handle);
        }

        return [$created, $added];
    }

    /**
     * New mail in the IMAP folder: the messages after the last UID handled. The first time (or when
     * the folder's UIDs start over) that's set to the newest message, so older mail isn't turned into
     * tickets. The place only moves on once a message is handled; a message handled twice is skipped
     * by its id.
     */
    private static function fetchImap(callable $handle): void
    {
        $config = static::imapConfig();
        $imap = new TomYumImap($config['host'], $config['port']);
        try {
            $imap->login($config['username'], $config['password']);
            $box = $imap->examine($config['folder']);

            $state = Cache::get('tomyum:tickets:imap');
            if (!is_array($state) || ($state['validity'] ?? null) !== $box['uidvalidity']) {
                Cache::forever('tomyum:tickets:imap', ['validity' => $box['uidvalidity'], 'last' => $box['uidnext'] - 1]);

                return;
            }

            // "n:*" always includes the newest message, even when it's older than n.
            $uids = array_values(array_filter($imap->search('UID '.($state['last'] + 1).':*'), fn ($uid) => $uid > $state['last']));
            foreach (array_slice($uids, 0, 50) as $uid) {
                $fetched = $imap->fetch($uid);
                if ($fetched !== null) {
                    $handle(static::fromImap($fetched, $box['uidvalidity'], $uid));
                }
                Cache::forever('tomyum:tickets:imap', ['validity' => $box['uidvalidity'], 'last' => $uid]);
            }
        } finally {
            $imap->logout();
        }
    }

    private static function fromImap(array $fetched, int $validity, int $uid): array
    {
        $message = TomYumMailMessage::parse($fetched['raw']);

        $receivedAt = null;
        foreach ([[$fetched['date'], 'd-M-Y H:i:s O'], [$message->header('date'), null]] as [$date, $format]) {
            try {
                $receivedAt ??= $date === null || $date === '' ? null
                    : ($format === null ? Carbon::parse($date) : Carbon::createFromFormat($format, $date));
            } catch (\Throwable) {
                // An unreadable date: try the next, or now.
            }
        }

        // Message-IDs can be longer than the column: a hash of it (or of the folder's UID) instead.
        $id = $message->header('message-id') ?: "{$validity}:{$uid}";

        return [
            'id' => 'imap:'.sha1($id),
            'to' => implode(' ', [$message->header('to'), $message->header('cc'), $message->header('delivered-to'), $message->header('x-original-to')]),
            'from' => $message->header('from'),
            'name' => $message->fromName(),
            'subject' => $message->header('subject'),
            'body' => $message->text,
            'is_summary' => false,
            'received_at' => $receivedAt ?? Carbon::now(),
            'automated' => $message->isAutomated(),
        ];
    }

    /** The Inbox's latest 50 emails through NoCodeAPI's Zoho connector (the start of each). */
    private static function fetchZoho(callable $handle): void
    {
        $base = static::apiBase();

        // Mail from before the system was turned on isn't turned into tickets.
        $since = (int) Cache::rememberForever('tomyum:tickets:since', fn () => Carbon::now()->getTimestampMs());

        $response = Http::timeout(30)->get("{$base}/listEmails", ['folderId' => static::inboxId($base), 'limit' => 50]);
        $emails = $response->json('data');
        if (!is_array($emails)) {
            throw new \RuntimeException('The mailbox could not be read: '.$response->body());
        }

        foreach (array_reverse($emails) as $email) {
            $messageId = (string) ($email['messageId'] ?? '');
            if ($messageId === '' || (int) ($email['receivedTime'] ?? 0) < $since) {
                continue;
            }

            $handle([
                'id' => $messageId,
                'to' => html_entity_decode(($email['toAddress'] ?? '').' '.($email['ccAddress'] ?? '')),
                'from' => html_entity_decode($email['fromAddress'] ?? ''),
                'name' => html_entity_decode($email['sender'] ?? ''),
                'subject' => html_entity_decode($email['subject'] ?? ''),
                'body' => html_entity_decode($email['summary'] ?? ''),
                'is_summary' => true,
                'received_at' => Carbon::createFromTimestampMs((int) ($email['receivedTime'] ?? Carbon::now()->getTimestampMs())),
                'automated' => false,
            ]);
        }
    }

    private static function receive(string $category, string $from, array $email): string
    {
        $subject = trim($email['subject']) ?: '(no subject)';
        $name = trim($email['name']);
        $receivedAt = $email['received_at'];
        $body = trim($email['body']);
        $isSummary = $email['is_summary'];
        $messageId = $email['id'];

        $ticket = null;
        if (preg_match('/\[Ticket #(\d+)\]/i', $subject, $match)) {
            $ticket = DB::table('tomyum_tickets')->where('id', (int) $match[1])->where('requester_email', $from)->first();
        }

        [$isNew, $ticketId] = DB::transaction(function () use ($ticket, $category, $from, $name, $subject, $receivedAt, $body, $isSummary, $messageId) {
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
                'is_summary' => $isSummary,
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

    /**
     * A ticket sent from the website (the appeal form) by a signed-in player: the same as one sent by
     * email from their account's address, so replies reach them there. Returns its id.
     */
    public static function createFromSite(User $user, string $category, string $subject, string $body): int
    {
        $now = Carbon::now();
        $ticketId = DB::transaction(function () use ($user, $category, $subject, $body, $now) {
            $ticketId = DB::table('tomyum_tickets')->insertGetId([
                'category' => $category,
                'status' => 'open',
                'subject' => mb_substr($subject, 0, 255),
                'requester_email' => strtolower($user->user_email),
                'requester_name' => mb_substr($user->username, 0, 255),
                'user_id' => $user->getKey(),
                'mailbox' => static::address($category),
                'last_message_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('tomyum_ticket_messages')->insert([
                'ticket_id' => $ticketId,
                'direction' => 'in',
                'from_address' => strtolower($user->user_email),
                'subject' => mb_substr($subject, 0, 255),
                'body' => $body,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $ticketId;
        });

        static::sendAutoReply(DB::table('tomyum_tickets')->find($ticketId));

        return $ticketId;
    }

    // ── Mail out ─────────────────────────────────────────────────────────────────────────────

    public static function autoReplyText(object $ticket): string
    {
        $label = static::label($ticket->category);
        $server = $GLOBALS['cfg']['tomyum']['server_name'];
        $signature = static::signature();

        return <<<TEXT
            Hello,

            Thank you for contacting {$server}. This is an automatic message to let you know your email has reached us and is now ticket #{$ticket->id} ({$label}).

            What happens next:
            - General questions and account help: a member of our team usually replies within a day or two.
            - Restrictions and appeals: these take longer to look into, usually 3 to 14 days. If your appeal is still on cooldown, we can't review it yet; please send it again once the cooldown is over.
            - Reports about a player or abusive chat: please also report them in the game, so staff have the full context.

            If you'd like to add anything, reply to this email and keep "[Ticket #{$ticket->id}]" in the subject. Otherwise there's no need to reply: we'll be in touch.

            Regards,

            {$signature}
            TEXT;
    }

    /** A staff reply, in the usual greeting and signature. */
    public static function replyText(string $message): string
    {
        return "Hello,\n\n".trim($message)."\n\nRegards,\n\n".static::signature();
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
     * Sends from the ticket's address; if the mail server refuses it (not set up as an alias), from
     * the server's main address instead, so the requester still gets an answer.
     */
    private static function send(object $ticket, string $text): bool
    {
        if (static::imapConfig() !== null) {
            return static::sendSmtp($ticket, $text);
        }

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

    /** The same through the site's mail server (MAIL_*), replies coming back to the ticket's address. */
    private static function sendSmtp(object $ticket, string $text): bool
    {
        $fallback = $GLOBALS['cfg']['mail']['from']['address'];
        foreach (array_unique([$ticket->mailbox, $fallback]) as $from) {
            try {
                Mail::raw($text, function ($message) use ($ticket, $from) {
                    $message->from($from, static::signature())
                        ->replyTo($ticket->mailbox)
                        ->to($ticket->requester_email)
                        ->subject(static::subjectFor($ticket));
                });

                return true;
            } catch (\Throwable $e) {
                \Log::warning("Ticket #{$ticket->id}: sending from {$from} failed: {$e->getMessage()}");
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

    /** The IMAP mailbox's settings, or null when tickets come through NoCodeAPI (or not at all). */
    private static function imapConfig(): ?array
    {
        $config = $GLOBALS['cfg']['tomyum']['tickets']['imap'] ?? null;

        return ($config['host'] ?? null) !== null && ($config['username'] ?? null) !== null && ($config['password'] ?? null) !== null
            ? $config
            : null;
    }

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
