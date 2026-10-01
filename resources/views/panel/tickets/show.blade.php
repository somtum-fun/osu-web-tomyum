{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    use App\Libraries\TomYumTickets;
    $ticketId = $ticket->id;
    $account = $ticket->user_id !== null ? ($people[$ticket->user_id] ?? null) : null;
    $closer = $ticket->closed_by !== null ? ($people[$ticket->closed_by] ?? null) : null;
    $when = fn ($date) => $date === null ? '—' : Carbon\Carbon::parse($date)->format('j M Y, H:i').' UTC';
    $kinds = [
        'in' => ['From', 'fas fa-envelope', ''],
        'out' => ['Reply', 'fas fa-reply', 'border-left: 3px solid hsl(var(--hsl-lime-1))'],
        'auto' => ['Automatic reply', 'fas fa-robot', 'opacity: 0.75'],
        'note' => ['Staff note', 'fas fa-sticky-note', 'border-left: 3px solid hsl(var(--hsl-orange-1))'],
    ];
@endphp
@extends('master', ['titlePrepend' => "Ticket #{$ticketId}"])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.tickets.index'), 'title' => 'Tickets'],
        ['title' => "#{$ticketId}"],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2 style="margin-bottom: 4px">
                <i class="fas fa-ticket-alt"></i>#{{ $ticketId }} {{ $ticket->subject }}
                <span class="tomyum-panel__badge {{ $ticket->status === 'open' ? 'tomyum-panel__badge--success' : '' }}">{{ ucfirst($ticket->status) }}</span>
            </h2>
            <table class="tomyum-panel__table">
                <tr><td style="width: 160px">Category</td><td>
                    <form method="POST" action="{{ route('panel.tickets.category', ['ticket' => $ticketId]) }}" class="tomyum-panel__row" style="align-items: center">
                        @csrf
                        <select class="input-text" name="category" style="width: auto" onchange="this.form.submit()">
                            @foreach ($categories as $c)
                                <option value="{{ $c }}" @selected($c === $ticket->category)>{{ TomYumTickets::label($c) }}</option>
                            @endforeach
                        </select>
                        <span class="tomyum-panel__hint">sent to {{ $ticket->mailbox }}</span>
                    </form>
                </td></tr>
                <tr><td>From</td><td>
                    {{ $ticket->requester_name ? $ticket->requester_name.' · ' : '' }}{{ $ticket->requester_email }}
                    @if ($account !== null)
                        · account <a href="{{ route('users.show', ['user' => $account->getKey()]) }}">{{ $account->username }}</a>
                        @if (App\Http\Controllers\Panel\Controller::canUseAdmin(Auth::user()))
                            (<a href="{{ route('panel.admin.users.show', ['user' => $account->getKey()]) }}">admin</a>)
                        @endif
                    @else
                        <span class="tomyum-panel__hint">· no account with this email</span>
                    @endif
                </td></tr>
                <tr><td>Opened</td><td>{{ $when($ticket->created_at) }}</td></tr>
                <tr><td>Last message</td><td>{{ $when($ticket->last_message_at) }}</td></tr>
                @if ($ticket->status === 'closed')
                    <tr><td>Closed</td><td>{{ $when($ticket->closed_at) }}{{ $closer ? ' by '.$closer->username : '' }}</td></tr>
                    <tr><td>Reason</td><td>{{ $ticket->close_reason }}</td></tr>
                @endif
                <tr><td>Handled by</td><td>
                    <div class="tomyum-panel__row" style="gap: 6px; align-items: center; flex-wrap: wrap">
                        @forelse ($handlers as $handler)
                            <form method="POST" action="{{ route('panel.tickets.handlers', ['ticket' => $ticketId]) }}" style="display: inline">
                                @csrf
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="user" value="{{ $handler->getKey() }}">
                                <span class="tomyum-panel__badge">{{ $handler->username }} <button type="submit" title="Remove" style="background: none; border: 0; color: inherit; cursor: pointer">×</button></span>
                            </form>
                        @empty
                            <span class="tomyum-panel__hint">nobody yet</span>
                        @endforelse
                        @if (!$handlers->contains(fn ($h) => $h->getKey() === Auth::user()->getKey()))
                            <form method="POST" action="{{ route('panel.tickets.handlers', ['ticket' => $ticketId]) }}" style="display: inline">
                                @csrf
                                <input type="hidden" name="action" value="take">
                                <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" type="submit">Take it</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('panel.tickets.handlers', ['ticket' => $ticketId]) }}" class="tomyum-panel__row" style="align-items: center">
                            @csrf
                            <input type="hidden" name="action" value="add">
                            <input class="input-text" type="text" name="user" placeholder="add staff (name or id)" style="width: 180px">
                            <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" type="submit">Add</button>
                        </form>
                    </div>
                </td></tr>
            </table>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-comments"></i>Conversation</h2>
            @foreach ($messages as $message)
                @php
                    [$kind, $icon, $style] = $kinds[$message->direction] ?? $kinds['in'];
                    $author = $message->author_id !== null ? ($people[$message->author_id] ?? null) : null;
                @endphp
                <div class="tomyum-panel__card" style="margin: 0 0 10px; {{ $style }}">
                    <p class="tomyum-panel__hint" style="margin: 0 0 6px">
                        <i class="{{ $icon }}"></i>
                        {{ $kind }}
                        @if ($message->direction === 'in')
                            {{ $message->from_address }}
                        @elseif ($author !== null)
                            by {{ $author->username }}
                        @endif
                        · {{ $when($message->created_at) }}
                    </p>
                    <div style="white-space: pre-wrap; word-break: break-word">{{ $message->body }}</div>
                    @if ($message->is_summary && $message->mail_message_id)
                        <p class="tomyum-panel__hint" style="margin: 6px 0 0">
                            Only the start of the email is shown here.
                            <a href="{{ TomYumTickets::webMailUrl($message->mail_message_id) }}" target="_blank" rel="noopener">Read the whole email in Zoho Mail</a>
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="tomyum-panel__card" id="reply">
            <h2><i class="fas fa-reply"></i>Reply</h2>
            <p class="tomyum-panel__hint">
                Goes to {{ $ticket->requester_email }} from {{ $ticket->mailbox }}, as "Hello, … Regards, production support", with
                "[Ticket #{{ $ticketId }}]" in the subject so their answer comes back here.
            </p>
            <form method="POST" action="{{ route('panel.tickets.reply', ['ticket' => $ticketId]) }}" style="display: grid; gap: 10px">
                @csrf
                <textarea class="input-text" name="message" rows="8" style="width: 100%" placeholder="Your message (without greeting or signature)" required></textarea>
                <div class="tomyum-panel__row" style="align-items: flex-end">
                    <label class="tomyum-panel__check"><input type="checkbox" name="close" value="1"> Close the ticket after sending</label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Reason (when closing)</span>
                        <input class="input-text" type="text" name="reason" maxlength="500" placeholder="Answered">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-paper-plane"></i>Send reply</button>
                </div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-sticky-note"></i>Staff note</h2>
            <form method="POST" action="{{ route('panel.tickets.note', ['ticket' => $ticketId]) }}" class="tomyum-panel__row" style="align-items: flex-end">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Only staff see notes</span>
                    <input class="input-text" type="text" name="note" maxlength="5000" required>
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit">Add note</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            @if ($ticket->status === 'open')
                <h2><i class="fas fa-check"></i>Close</h2>
                <form method="POST" action="{{ route('panel.tickets.close', ['ticket' => $ticketId]) }}" class="tomyum-panel__row" style="align-items: flex-end">
                    @csrf
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Reason</span>
                        <input class="input-text" type="text" name="reason" maxlength="500" required placeholder="Resolved / Duplicate of #… / No reply">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-check"></i>Close ticket</button>
                </form>
            @else
                <h2><i class="fas fa-undo"></i>Reopen</h2>
                <p class="tomyum-panel__hint">A reply from {{ $ticket->requester_email }} reopens it by itself.</p>
                <form method="POST" action="{{ route('panel.tickets.reopen', ['ticket' => $ticketId]) }}">
                    @csrf
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit"><i class="fas fa-undo"></i>Reopen</button>
                </form>
            @endif
        </div>
    </div>
@endsection
