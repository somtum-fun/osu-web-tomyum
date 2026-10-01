{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Bots'])

@section('content')
    @include('panel._header', ['crumbs' => [['title' => 'Bots']]])
    <div class="osu-page osu-page--admin tomyum-panel">
        @if ($canCreate)
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-plus"></i>Make a bot</h2>
                <p class="tomyum-panel__hint">
                    A bot account is in the Chat Bots group. It chats over IRC (like osu!'s bots) and can use the website
                    (teams, the forum), but it can't log in to stable or lazer and can't submit scores. Its owner manages it
                    and gets its website verification codes by email.
                </p>
                <form method="POST" action="{{ route('panel.bots.store') }}" class="tomyum-panel__row" style="align-items: flex-end" autocomplete="off">
                    @csrf
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 180px">
                        <span class="input-container__label">Bot name</span>
                        <input class="input-text" type="text" name="username" maxlength="15" required>
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 180px">
                        <span class="input-container__label">Owner (name or id)</span>
                        <input class="input-text" type="text" name="owner" placeholder="{{ Auth::user()->username }}">
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                        <span class="input-container__label">Website password (optional)</span>
                        <input class="input-text" type="text" name="password" minlength="8" placeholder="random" autocomplete="new-password">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">What it does (optional)</span>
                        <input class="input-text" type="text" name="description" maxlength="255">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-robot"></i>Make bot</button>
                </form>
            </div>
        @endif

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-robot"></i>{{ $canCreate ? 'Bots' : 'Your bots' }}</h2>
            @if ($bots->isEmpty())
                <p class="tomyum-panel__empty">No bots yet.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th></th><th>Bot</th><th>Owner</th><th>What it does</th><th>Made</th><th></th></tr>
                    @foreach ($bots as $bot)
                        @php
                            $owner = $owners[$bot->record?->owner_id ?? 0] ?? null;
                        @endphp
                        <tr>
                            <td style="width: 40px">
                                <img src="{{ App\Libraries\User\AvatarHelper::url($bot->user) }}" alt="" style="border-radius: 8px; height: 32px; width: 32px; object-fit: cover">
                            </td>
                            <td><a href="{{ route('users.show', ['user' => $bot->user->getKey()]) }}">{{ $bot->user->username }}</a> <span class="tomyum-panel__hint">#{{ $bot->user->getKey() }}</span></td>
                            <td>
                                @if ($owner !== null)
                                    <a href="{{ route('users.show', ['user' => $owner->getKey()]) }}">{{ $owner->username }}</a>
                                @else
                                    <span class="tomyum-panel__hint">{{ $bot->record === null ? 'built in' : 'nobody' }}</span>
                                @endif
                            </td>
                            <td>{{ $bot->record?->description }}</td>
                            <td style="white-space: nowrap">{!! $bot->user->user_regdate ? timeago($bot->user->user_regdate) : '—' !!}</td>
                            <td style="width: 1%">
                                @if (!App\Libraries\TomYumBots::isBanchoBot($bot->user))
                                    <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('panel.bots.show', ['bot' => $bot->user->getKey()]) }}"><i class="fas fa-cog"></i>Manage</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
@endsection
