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
                <i class="fas fa-ticket-alt"></i>
                <span style="flex: 1; min-width: 0; overflow-wrap: anywhere">#{{ $ticketId }} {{ $ticket->subject }}</span>
                <span class="tomyum-panel__badge {{ $ticket->status === 'open' ? 'tomyum-panel__badge--success' : '' }}" style="flex: none">{{ ucfirst($ticket->status) }}</span>
            </h2>
            <table class="tomyum-panel__table" style="table-layout: fixed; width: 100%">
                <tr><td style="width: 160px">Category</td><td style="overflow-wrap: anywhere">
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
                <tr><td>From</td><td style="overflow-wrap: anywhere">
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
                            @if (str_starts_with($message->mail_message_id, 'imap:'))
                                This email was too big to read whole: only its text is here. Its files are in the mailbox.
                            @else
                                Only the start of the email is shown here.
                                <a href="{{ TomYumTickets::webMailUrl($message->mail_message_id) }}" target="_blank" rel="noopener">Read the whole email in Zoho Mail</a>
                            @endif
                        </p>
                    @endif

                    {{-- TomYum: the email's files; pictures shown, anything else to download. --}}
                    @php
                        $files = $attachments[$message->id] ?? collect();
                        $pictures = $files->filter(fn ($file) => in_array($file->mime, TomYumTickets::IMAGE_TYPES, true));
                        $others = $files->diffKeys($pictures);
                        $fileUrl = fn ($file, $download = false) => route('panel.tickets.attachment', ['ticket' => $ticketId, 'attachment' => $file->id] + ($download ? ['download' => 1] : []));
                        $size = fn ($bytes) => $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, round($bytes / 1024)).' KB';
                    @endphp
                    @if ($pictures->isNotEmpty())
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px">
                            @foreach ($pictures as $file)
                                <a href="{{ $fileUrl($file) }}" target="_blank" rel="noopener" title="{{ $file->filename }} ({{ $size($file->size) }})">
                                    <img src="{{ $fileUrl($file) }}" alt="{{ $file->filename }}" loading="lazy" style="display: block; max-width: min(240px, 100%); max-height: 180px; border-radius: 4px; background: hsl(var(--hsl-b6))">
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if ($others->isNotEmpty())
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px">
                            @foreach ($others as $file)
                                <a class="tomyum-panel__badge" href="{{ $fileUrl($file, true) }}" style="overflow-wrap: anywhere">
                                    <i class="fas fa-paperclip"></i> {{ $file->filename }} · {{ $size($file->size) }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($message->quoted !== null)
                        <details style="margin-top: 8px">
                            <summary class="tomyum-panel__hint" style="cursor: pointer">Show the quoted earlier messages</summary>
                            <div style="white-space: pre-wrap; word-break: break-word; margin-top: 6px; padding-left: 10px; border-left: 2px solid hsl(var(--hsl-b2)); opacity: 0.8">{{ $message->quoted }}</div>
                        </details>
                    @endif
                    @if ($message->html !== null)
                        <details style="margin-top: 8px">
                            <summary class="tomyum-panel__hint" style="cursor: pointer">Show the email as it was formatted</summary>
                            {{-- Cleaned, in a sandbox without scripts; outside pictures aren't loaded (they'd tell the sender who read it). --}}
                            <div style="height: 360px; margin-top: 6px; overflow: hidden; resize: vertical; border-radius: 4px; background: #fff">
                                <iframe
                                    src="{{ route('panel.tickets.html', ['ticket' => $ticketId, 'message' => $message->id]) }}"
                                    sandbox="allow-popups allow-popups-to-escape-sandbox"
                                    referrerpolicy="no-referrer"
                                    loading="lazy"
                                    title="The email as it was formatted"
                                    style="width: 100%; height: 100%; border: 0; display: block"
                                ></iframe>
                            </div>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="tomyum-panel__card" id="reply">
            <h2><i class="fas fa-reply"></i>Reply</h2>
            <p class="tomyum-panel__hint">
                Goes to {{ $ticket->requester_email }} from {{ $ticket->mailbox }}, as "Hello, … Regards, {{ App\Libraries\TomYumTickets::signature() }}", with
                "[Ticket #{{ $ticketId }}]" in the subject, as an answer to their last email, so their mail app keeps it in the same
                conversation and their answer comes back here.
            </p>
            <form method="POST" action="{{ route('panel.tickets.reply', ['ticket' => $ticketId]) }}" enctype="multipart/form-data" style="display: grid; gap: 10px">
                @csrf
                <textarea class="input-text" name="message" rows="8" style="width: 100%" placeholder="Your message (without greeting or signature)" required></textarea>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Attach files (up to 10, 20 MB in all)</span>
                    <input class="input-text" type="file" name="files[]" multiple>
                </label>
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
                    <textarea class="input-text" name="note" rows="3" maxlength="5000" required></textarea>
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
                    {{-- TomYum: a denied appeal waits 3 more months (TomYumAppeals). --}}
                    @if ($ticket->category === 'appeal' && $ticket->user_id !== null)
                        <label class="tomyum-panel__check"><input type="checkbox" name="appeal_denied" value="1"> Appeal denied (next appeal in 3 months)</label>
                    @endif
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
