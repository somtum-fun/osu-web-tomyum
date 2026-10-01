{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    use App\Libraries\TomYumTickets;
    $link = fn (array $change) => route('panel.tickets.index', array_filter(array_merge(['category' => $category, 'status' => $status, 'mine' => $mine ? 1 : null, 'q' => $query ?: null], $change), fn ($v) => $v !== null));
@endphp
@extends('master', ['titlePrepend' => 'Tickets'])

@section('content')
    @include('panel._header', ['crumbs' => [['title' => 'Tickets']]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-inbox"></i>Tickets</h2>
            <p class="tomyum-panel__hint">
                Emails to the server's addresses, by category. Each new email gets an automatic reply with its ticket number; replies
                you send here go from the address it came to. You see the categories your group handles.
            </p>
            <div class="tomyum-panel__row" style="gap: 6px; flex-wrap: wrap; margin-bottom: 10px">
                <a class="tomyum-panel__badge {{ $category === null ? 'tomyum-panel__badge--success' : '' }}" href="{{ $link(['category' => null, 'page' => null]) }}">All</a>
                @foreach ($categories as $c)
                    <a class="tomyum-panel__badge {{ $category === $c ? 'tomyum-panel__badge--success' : '' }}" href="{{ $link(['category' => $c, 'page' => null]) }}">
                        {{ TomYumTickets::label($c) }}@if (($counts[$c] ?? 0) > 0) ({{ $counts[$c] }})@endif
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('panel.tickets.index') }}" class="tomyum-panel__row" style="align-items: flex-end">
                @if ($category !== null)
                    <input type="hidden" name="category" value="{{ $category }}">
                @endif
                <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 150px">
                    <span class="input-container__label">Status</span>
                    <select class="input-text" name="status" onchange="this.form.submit()">
                        @foreach (['open' => 'Open', 'closed' => 'Closed', 'all' => 'All'] as $value => $text)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Search (subject, email or #number)</span>
                    <input class="input-text" type="text" name="q" value="{{ $query }}">
                </label>
                <label class="tomyum-panel__check"><input type="checkbox" name="mine" value="1" @checked($mine) onchange="this.form.submit()"> Mine</label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-search"></i>Find</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            @if ($tickets->isEmpty())
                <p class="tomyum-panel__empty">No tickets here.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>#</th><th>Category</th><th>Subject</th><th>From</th><th>Opened</th><th>Last message</th><th>Handled by</th><th>Status</th></tr>
                    @foreach ($tickets as $ticket)
                        @php
                            $account = $ticket->user_id !== null ? ($users[$ticket->user_id] ?? null) : null;
                        @endphp
                        <tr>
                            <td><a href="{{ route('panel.tickets.show', ['ticket' => $ticket->id]) }}">#{{ $ticket->id }}</a></td>
                            <td style="white-space: nowrap">{{ TomYumTickets::label($ticket->category) }}</td>
                            <td><a href="{{ route('panel.tickets.show', ['ticket' => $ticket->id]) }}">{{ $ticket->subject }}</a></td>
                            <td>
                                @if ($account !== null)
                                    <a href="{{ route('users.show', ['user' => $account->getKey()]) }}">{{ $account->username }}</a><br>
                                @endif
                                <span class="tomyum-panel__hint">{{ $ticket->requester_email }}</span>
                            </td>
                            <td style="white-space: nowrap">{!! timeago(Carbon\Carbon::parse($ticket->created_at)) !!}</td>
                            <td style="white-space: nowrap">{!! $ticket->last_message_at ? timeago(Carbon\Carbon::parse($ticket->last_message_at)) : '—' !!}</td>
                            <td>{{ ($handlers[$ticket->id] ?? collect())->pluck('username')->join(', ') ?: '—' }}</td>
                            <td>
                                <span class="tomyum-panel__badge {{ $ticket->status === 'open' ? 'tomyum-panel__badge--success' : '' }}">{{ ucfirst($ticket->status) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </table>
                @if ($tickets->hasPages())
                    <p class="tomyum-panel__row" style="justify-content: space-between">
                        @if ($tickets->previousPageUrl())<a href="{{ $tickets->previousPageUrl() }}">← Newer</a>@else<span></span>@endif
                        <span class="tomyum-panel__hint">Page {{ $tickets->currentPage() }} of {{ $tickets->lastPage() }}</span>
                        @if ($tickets->nextPageUrl())<a href="{{ $tickets->nextPageUrl() }}">Older →</a>@else<span></span>@endif
                    </p>
                @endif
            @endif
        </div>
    </div>
@endsection
