<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumTickets;
use App\Models\User;
use Auth;
use DB;
use Request;

/**
 * Support tickets (TomYum): emails to the server's addresses, by category. Each staff member sees the
 * categories their group handles (TomYumTickets::categoriesFor); they reply by email from the panel,
 * leave notes for each other, take a ticket on (handlers) and close it with a reason.
 */
class TicketsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(count(TomYumTickets::categoriesFor(Auth::user())) > 0);

            return $next($request);
        });
    }

    public static function canUseTickets(?User $user): bool
    {
        return count(TomYumTickets::categoriesFor($user)) > 0;
    }

    public function index()
    {
        $categories = TomYumTickets::categoriesFor(Auth::user());
        $category = Request::input('category');
        if (!in_array($category, $categories, true)) {
            $category = null;
        }
        $status = in_array(Request::input('status'), ['open', 'closed', 'all'], true) ? Request::input('status') : 'open';
        $mine = Request::boolean('mine');
        $query = trim(Request::input('q') ?? '');

        $tickets = DB::table('tomyum_tickets')
            ->whereIn('category', $category === null ? $categories : [$category])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($mine, fn ($q) => $q->whereIn('id', DB::table('tomyum_ticket_handlers')->where('user_id', Auth::user()->getKey())->select('ticket_id')))
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($q) use ($query) {
                    $q->where('subject', 'like', '%'.$query.'%')->orWhere('requester_email', 'like', '%'.$query.'%');
                    if (ctype_digit(ltrim($query, '#'))) {
                        $q->orWhere('id', (int) ltrim($query, '#'));
                    }
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        $counts = DB::table('tomyum_tickets')->whereIn('category', $categories)->where('status', 'open')
            ->groupBy('category')->selectRaw('category, count(*) n')->pluck('n', 'category');

        return ext_view('panel.tickets.index', [
            'tickets' => $tickets,
            'categories' => $categories,
            'category' => $category,
            'status' => $status,
            'mine' => $mine,
            'query' => $query,
            'counts' => $counts,
            'handlers' => $this->handlersOf($tickets->pluck('id')->all()),
            'users' => User::whereIn('user_id', collect($tickets->items())->pluck('user_id')->filter())->get()->keyBy('user_id'),
        ]);
    }

    public function show(string $ticket)
    {
        $row = $this->ticket($ticket);
        $messages = DB::table('tomyum_ticket_messages')->where('ticket_id', $row->id)->orderBy('created_at')->orderBy('id')->get();
        $handlers = $this->handlersOf([$row->id])[$row->id] ?? collect();
        $people = User::whereIn('user_id', $messages->pluck('author_id')->merge([$row->closed_by, $row->user_id])->filter()->unique())->get()->keyBy('user_id');
        $attachments = DB::table('tomyum_ticket_attachments')->whereIn('message_id', $messages->pluck('id'))->orderBy('id')->get()->groupBy('message_id');

        return ext_view('panel.tickets.show', [
            'ticket' => $row,
            'messages' => $messages,
            'attachments' => $attachments,
            'handlers' => $handlers,
            'people' => $people,
            'categories' => TomYumTickets::categoriesFor(Auth::user()),
        ]);
    }

    /** An attachment: pictures shown, anything else downloaded; never run by the browser. */
    public function attachment(string $ticket, string $attachment)
    {
        $row = $this->ticket($ticket);
        $file = DB::table('tomyum_ticket_attachments')
            ->join('tomyum_ticket_messages', 'tomyum_ticket_messages.id', '=', 'tomyum_ticket_attachments.message_id')
            ->where('tomyum_ticket_messages.ticket_id', $row->id)
            ->where('tomyum_ticket_attachments.id', (int) $attachment)
            ->select('tomyum_ticket_attachments.*')
            ->first();
        abort_if($file === null, 404);
        $data = TomYumTickets::attachmentData($file);
        abort_if($data === null, 404);

        $image = Request::boolean('download') ? null : TomYumTickets::imageType($data);
        $name = str_replace(['"', '\\'], '_', $file->filename);

        return response($data, 200, [
            'Content-Type' => $image ?? 'application/octet-stream',
            'Content-Disposition' => ($image === null ? 'attachment' : 'inline').'; filename="'.preg_replace('/[^\x20-\x7e]/', '_', $name).'"; filename*=UTF-8\'\''.rawurlencode($file->filename),
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** An email's HTML, cleaned (TomYumTickets::cleanHtml), for the sandboxed frame on the ticket. */
    public function html(string $ticket, string $message)
    {
        $row = $this->ticket($ticket);
        $email = DB::table('tomyum_ticket_messages')->where('ticket_id', $row->id)->where('id', (int) $message)->first();
        abort_if($email === null || $email->html === null, 404);
        $attachments = DB::table('tomyum_ticket_attachments')->where('message_id', $email->id)->get();

        $body = TomYumTickets::cleanHtml($email->html, $attachments);
        $page = '<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank">'
            .'<style>body{background:#fff;color:#222;font:14px/1.5 sans-serif;margin:12px;overflow-wrap:anywhere}img{height:auto;max-width:100%}</style>'
            .'</head><body>'.$body.'</body></html>';

        return response($page, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            // Nothing from outside (the cleaning already took out outside pictures), no scripts.
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'; font-src data:; sandbox allow-popups allow-popups-to-escape-sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function reply(string $ticket)
    {
        $row = $this->ticket($ticket);
        $message = trim(Request::input('message') ?? '');
        if ($message === '') {
            return $this->back($row, 'error', 'Write a reply.');
        }

        // Files to send with it: up to 10, 20 MB in all.
        $files = [];
        $total = 0;
        foreach (Request::file('files') ?? [] as $upload) {
            if ($upload === null) {
                continue;
            }
            if (!$upload->isValid()) {
                return $this->back($row, 'error', "{$upload->getClientOriginalName()} couldn't be uploaded.");
            }
            $total += $upload->getSize();
            if (count($files) >= 10 || $total > 20 * 1024 * 1024) {
                return $this->back($row, 'error', 'Attach at most 10 files, 20 MB in all.');
            }
            $files[] = [
                'filename' => $upload->getClientOriginalName(),
                'mime' => $upload->getMimeType() ?? 'application/octet-stream',
                'data' => $upload->get(),
            ];
        }

        $error = TomYumTickets::reply($row, Auth::user(), $message, $files);
        if ($error !== null) {
            return $this->back($row, 'error', $error);
        }
        if (Request::boolean('close')) {
            TomYumTickets::close($row, Auth::user(), trim(Request::input('reason') ?? '') ?: 'Answered');
        }

        return $this->back($row, 'success', 'Reply sent to '.$row->requester_email.(Request::boolean('close') ? ', and the ticket is closed.' : '.'));
    }

    public function note(string $ticket)
    {
        $row = $this->ticket($ticket);
        $text = trim(Request::input('note') ?? '');
        if ($text === '') {
            return $this->back($row, 'error', 'Write a note.');
        }
        TomYumTickets::note($row, Auth::user(), mb_substr($text, 0, 5000));

        return $this->back($row, 'success', 'Note added (staff only).');
    }

    public function close(string $ticket)
    {
        $row = $this->ticket($ticket);
        $reason = trim(Request::input('reason') ?? '');
        if ($reason === '') {
            return $this->back($row, 'error', 'Give a reason for closing it.');
        }
        TomYumTickets::close($row, Auth::user(), $reason);

        // TomYum: a denied appeal waits 3 more months before the next one (TomYumAppeals).
        if ($row->category === 'appeal' && $row->user_id !== null && Request::boolean('appeal_denied')) {
            $after = \App\Libraries\TomYumAppeals::denied($row->user_id, Auth::user()->getKey());
            if ($after !== null) {
                TomYumTickets::note($row, Auth::user(), 'Appeal denied: they can appeal again from '.$after->format('j M Y').'.');

                return $this->back($row, 'success', 'Ticket closed. Appeal denied: they can appeal again from '.$after->format('j M Y').'.');
            }
        }

        return $this->back($row, 'success', 'Ticket closed.');
    }

    public function reopen(string $ticket)
    {
        $row = $this->ticket($ticket);
        TomYumTickets::reopen($row);

        return $this->back($row, 'success', 'Ticket reopened.');
    }

    public function handlers(string $ticket)
    {
        $row = $this->ticket($ticket);
        $action = Request::input('action');
        if ($action === 'take') {
            TomYumTickets::addHandler($row->id, Auth::user()->getKey());

            return $this->back($row, 'success', "You're handling this ticket.");
        }

        $user = $this->findUser(Request::input('user'));
        if ($user === null) {
            return $this->back($row, 'error', 'No such staff member.');
        }
        if ($action === 'remove') {
            TomYumTickets::removeHandler($row->id, $user->getKey());

            return $this->back($row, 'success', "{$user->username} is no longer handling it.");
        }
        if (!TomYumTickets::canAccess($user, $row->category)) {
            return $this->back($row, 'error', "{$user->username} can't see ".TomYumTickets::label($row->category).' tickets.');
        }
        TomYumTickets::addHandler($row->id, $user->getKey());

        return $this->back($row, 'success', "{$user->username} is handling it too.");
    }

    public function category(string $ticket)
    {
        $row = $this->ticket($ticket);
        $category = Request::input('category');
        if (!TomYumTickets::canAccess(Auth::user(), $category)) {
            return $this->back($row, 'error', 'Pick a category you can see.');
        }
        DB::table('tomyum_tickets')->where('id', $row->id)->update(['category' => $category, 'updated_at' => now()]);
        TomYumTickets::note($row, Auth::user(), 'Moved from '.TomYumTickets::label($row->category).' to '.TomYumTickets::label($category).'.');

        return $this->back($row, 'success', 'Moved to '.TomYumTickets::label($category).'.');
    }

    /** The ticket, if the current user may see its category (else 404/403). */
    private function ticket(string $id): object
    {
        $row = DB::table('tomyum_tickets')->find((int) $id);
        abort_if($row === null, 404);
        $this->ensure(TomYumTickets::canAccess(Auth::user(), $row->category));

        return $row;
    }

    /** ticket id => collection of handlers (users). */
    private function handlersOf(array $ticketIds)
    {
        $rows = DB::table('tomyum_ticket_handlers')->whereIn('ticket_id', $ticketIds)->orderBy('added_at')->get();
        $users = User::whereIn('user_id', $rows->pluck('user_id')->unique())->get()->keyBy('user_id');

        return $rows->groupBy('ticket_id')->map(fn ($group) => $group->map(fn ($row) => $users[$row->user_id] ?? null)->filter()->values());
    }

    private function findUser($input): ?User
    {
        $input = trim((string) $input);

        return $input === '' ? null : (ctype_digit($input) ? User::find((int) $input) : User::lookup($input, 'username'));
    }

    private function back(object $ticket, string $type, string $message)
    {
        return redirect(route('panel.tickets.show', ['ticket' => $ticket->id]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}
