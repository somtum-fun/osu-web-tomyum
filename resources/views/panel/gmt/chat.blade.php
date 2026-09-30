{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $oldest = count($messages) > 0 ? end($messages)['id'] : null;
@endphp
@extends('master', ['titlePrepend' => 'Chat logs'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.gmt.index'), 'title' => 'GMT panel'],
        ['title' => 'Chat logs'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-comments"></i>In-game chat</h2>
            <form method="GET" action="{{ route('panel.gmt.chat') }}" class="tomyum-panel__row">
                <label class="tomyum-panel__field">
                    Player (sent or received privately)
                    <input type="text" name="user" value="{{ $userQuery }}" placeholder="username or id">
                </label>
                <label class="tomyum-panel__field">
                    Channel or conversation
                    <input type="text" name="target" value="{{ $target }}" placeholder="#osu, #lobby, or a username">
                </label>
                <button class="tomyum-panel__button" type="submit">Show</button>
            </form>
            <p class="tomyum-panel__hint">
                Newest first. Kept for 30 days. Channel messages from before this panel existed weren't recorded.
                @if ($user)
                    &middot; <a href="{{ route('panel.gmt.users.show', ['user' => $user->getKey()]) }}">Open {{ $user->username }} in the GMT panel</a>
                @endif
            </p>
        </div>

        <div class="tomyum-panel__card">
            @if ($error !== null)
                <p>{{ $error }}</p>
            @elseif (count($messages) === 0)
                <p class="tomyum-panel__empty">No messages.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>When (UTC)</th><th>From</th><th>To</th><th>Message</th></tr>
                    @foreach ($messages as $message)
                        <tr>
                            <td style="white-space: nowrap">{{ Carbon\Carbon::parse($message['sentAt'], 'UTC')->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <a href="{{ route('panel.gmt.chat', ['user' => $message['senderId']]) }}">{{ $message['senderName'] }}</a>
                            </td>
                            <td>
                                <a href="{{ route('panel.gmt.chat', ['target' => $message['target']]) }}">{{ $message['target'] }}</a>
                            </td>
                            <td style="word-break: break-word">{{ $message['text'] }}</td>
                        </tr>
                    @endforeach
                </table>
                @if (count($messages) >= 100)
                    <p><a href="{{ route('panel.gmt.chat', array_filter(['user' => $userQuery, 'target' => $target, 'before' => $oldest])) }}">Older &rarr;</a></p>
                @endif
            @endif
        </div>
    </div>
@endsection
