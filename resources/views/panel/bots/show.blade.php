{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $botId = $bot->getKey();
@endphp
@extends('master', ['titlePrepend' => $bot->username])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.bots.index'), 'title' => 'Bots'],
        ['title' => $bot->username],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card tomyum-panel__row" style="align-items: center">
            <img src="{{ App\Libraries\User\AvatarHelper::url($bot) }}" alt="" style="border-radius: 12px; height: 64px; width: 64px; object-fit: cover">
            <div style="flex: 1">
                <h2 style="margin: 0">
                    <a href="{{ route('users.show', ['user' => $botId]) }}">{{ $bot->username }}</a>
                    <span class="tomyum-panel__hint">#{{ $botId }}</span>
                    <span class="tomyum-panel__badge">BOT</span>
                </h2>
                <p class="tomyum-panel__hint" style="margin: 4px 0 0">
                    owned by
                    @if ($owner !== null)
                        <a href="{{ route('users.show', ['user' => $owner->getKey()]) }}">{{ $owner->username }}</a>
                    @else
                        nobody
                    @endif
                    @if ($record?->description)
                        &middot; {{ $record->description }}
                    @endif
                </p>
            </div>
            @if ($canManageAll)
                <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('panel.admin.users.show', ['user' => $botId]) }}"><i class="fas fa-user-cog"></i>Account (admin)</a>
            @endif
        </div>

        <div class="tomyum-panel__card" id="irc">
            <h2><i class="fas fa-comments"></i>IRC</h2>
            <p class="tomyum-panel__hint">
                The bot chats like osu!'s bots: connect an IRC client or library to <code>{{ $ircHost }}</code>, port <code>6667</code>,
                with the nickname <code>{{ str_replace(' ', '_', $bot->username) }}</code> and this password. It's in the same channels and
                private messages as the game and the website.
            </p>
            <p>
                Password:
                @if ($ircKey === null)
                    <span class="tomyum-panel__hint">none yet</span>
                @else
                    <details style="display: inline"><summary>show</summary><code>{{ $ircKey }}</code></details>
                @endif
            </p>
            <div class="tomyum-panel__row" style="align-items: flex-end">
                <form method="POST" action="{{ route('panel.bots.irc', ['bot' => $botId]) }}" onsubmit="return confirm('Make a new IRC password? The old one stops working.')">
                    @csrf
                    <input type="hidden" name="action" value="reset">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-sync"></i>New random password</button>
                </form>
                <form method="POST" action="{{ route('panel.bots.irc', ['bot' => $botId]) }}" class="tomyum-panel__row" style="align-items: flex-end; flex: 1" autocomplete="off">
                    @csrf
                    <input type="hidden" name="action" value="set">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Or set one ({{ App\Libraries\TomYumBots::IRC_PASSWORD_MIN }}–{{ App\Libraries\TomYumBots::IRC_PASSWORD_MAX }} characters, no spaces)</span>
                        <input class="input-text" type="text" name="irc_password" minlength="{{ App\Libraries\TomYumBots::IRC_PASSWORD_MIN }}" maxlength="{{ App\Libraries\TomYumBots::IRC_PASSWORD_MAX }}" required autocomplete="new-password">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-key"></i>Set IRC password</button>
                </form>
            </div>
        </div>

        <div class="tomyum-panel__card" id="name">
            <h2><i class="fas fa-id-badge"></i>Name</h2>
            <form method="POST" action="{{ route('panel.bots.rename', ['bot' => $botId]) }}" class="tomyum-panel__row" style="align-items: flex-end">
                @csrf
                <label class="input-container tomyum-panel__field" style="flex: 0 1 220px">
                    <span class="input-container__label">New name</span>
                    <input class="input-text" type="text" name="username" maxlength="15" value="{{ $bot->username }}" required>
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-pen"></i>Rename</button>
            </form>
            <p class="tomyum-panel__hint">Its IRC nickname changes too (spaces become underscores).</p>
        </div>

        <div class="tomyum-panel__card" id="look">
            <h2><i class="fas fa-image"></i>Avatar and banner</h2>
            <div class="tomyum-panel__row" style="align-items: flex-end">
                <form method="POST" action="{{ route('panel.bots.avatar', ['bot' => $botId]) }}" enctype="multipart/form-data" class="tomyum-panel__row" style="align-items: flex-end; flex: 1">
                    @csrf
                    <label class="tomyum-panel__field">Avatar (square image)<input type="file" name="avatar_file" accept="image/*" required></label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-upload"></i>Upload avatar</button>
                </form>
                <form method="POST" action="{{ route('panel.bots.avatar', ['bot' => $botId]) }}">
                    @csrf
                    <input type="hidden" name="remove" value="1">
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit">Remove avatar</button>
                </form>
            </div>
            <div class="tomyum-panel__row" style="align-items: flex-end; margin-top: 12px">
                <form method="POST" action="{{ route('panel.bots.cover', ['bot' => $botId]) }}" enctype="multipart/form-data" class="tomyum-panel__row" style="align-items: flex-end; flex: 1">
                    @csrf
                    <label class="tomyum-panel__field">Profile banner (2000 × 500 or wider)<input type="file" name="cover_file" accept="image/*" required></label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-upload"></i>Upload banner</button>
                </form>
                <form method="POST" action="{{ route('panel.bots.cover', ['bot' => $botId]) }}">
                    @csrf
                    <input type="hidden" name="remove" value="1">
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit">Remove banner</button>
                </form>
            </div>
        </div>

        <div class="tomyum-panel__card" id="page">
            <h2><i class="fas fa-file-alt"></i>me! page</h2>
            <form method="POST" action="{{ route('panel.bots.page', ['bot' => $botId]) }}" style="display: grid; gap: 10px">
                @csrf
                <textarea class="input-text" name="body" rows="10" style="width: 100%; font-family: monospace" placeholder="BBCode, like the profile's me! section">{{ $bot->userPage?->bodyRaw }}</textarea>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save me! page</button></div>
            </form>
        </div>

        <div class="tomyum-panel__card" id="website">
            <h2><i class="fas fa-globe"></i>Website login</h2>
            <p class="tomyum-panel__hint">
                The bot can sign in to the website (to join a team or post in the forum) with its name and this password. New
                sessions are confirmed with a code sent to its owner's email. It can't log in to stable or lazer.
            </p>
            <form method="POST" action="{{ route('panel.bots.password', ['bot' => $botId]) }}" class="tomyum-panel__row" style="align-items: flex-end" autocomplete="off">
                @csrf
                <label class="input-container tomyum-panel__field" style="flex: 0 1 260px">
                    <span class="input-container__label">New website password (at least 8 characters)</span>
                    <input class="input-text" type="text" name="password" minlength="8" required autocomplete="new-password">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-key"></i>Set password</button>
            </form>
        </div>

        <div class="tomyum-panel__card" id="settings">
            <h2><i class="fas fa-cog"></i>About this bot</h2>
            <form method="POST" action="{{ route('panel.bots.settings', ['bot' => $botId]) }}" class="tomyum-panel__row" style="align-items: flex-end">
                @csrf
                @if ($canManageAll)
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                        <span class="input-container__label">Owner (name or id)</span>
                        <input class="input-text" type="text" name="owner" value="{{ $owner?->username }}">
                    </label>
                @endif
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">What it does</span>
                    <input class="input-text" type="text" name="description" maxlength="255" value="{{ $record?->description }}">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
            </form>
        </div>
    </div>
@endsection
