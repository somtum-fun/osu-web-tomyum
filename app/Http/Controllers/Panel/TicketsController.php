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

        return ext_view('panel.tickets.show', [
            'ticket' => $row,
            'messages' => $messages,
            'handlers' => $handlers,
            'people' => $people,
            'categories' => TomYumTickets::categoriesFor(Auth::user()),
        ]);
    }

    public function reply(string $ticket)
    {
        $row = $this->ticket($ticket);
        $message = trim(Request::input('message') ?? '');
        if ($message === '') {
            return $this->back($row, 'error', 'Write a reply.');
        }
        $error = TomYumTickets::reply($row, Auth::user(), $message);
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
