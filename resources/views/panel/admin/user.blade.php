{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $userId = $target->getKey();
    $targets = [$userId => $target->username];
@endphp
@extends('master', ['titlePrepend' => $target->username])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['url' => route('panel.admin.users.index'), 'title' => 'Accounts'],
        ['title' => $target->username],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card tomyum-panel__row" style="align-items: center">
            <img src="{{ App\Libraries\User\AvatarHelper::url($target) }}" alt="" style="border-radius: 12px; height: 64px; width: 64px; object-fit: cover">
            <div style="flex: 1">
                <h2 style="margin: 0">
                    <a href="{{ route('users.show', ['user' => $userId]) }}">{{ $target->username }}</a>
                    <span class="tomyum-panel__hint">#{{ $userId }}</span>
                </h2>
                <p class="tomyum-panel__hint" style="margin: 4px 0 0">
                    joined {{ $target->user_regdate?->toFormattedDateString() }}
                    &middot; last seen {!! $target->user_lastvisit ? timeago($target->user_lastvisit) : 'never' !!}
                    @if ($target->isRestricted())
                        &middot; <span class="tomyum-panel__badge tomyum-panel__badge--error">{{ $target->isBanned() ? 'Banned' : 'Restricted' }}</span>
                    @endif
                </p>
            </div>
            @if (App\Http\Controllers\Panel\Controller::canUseGmt(Auth::user()))
                <a href="{{ route('panel.gmt.users.show', ['user' => $userId]) }}">Moderation (GMT panel)</a>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-user"></i>Profile</h2>
            <p class="tomyum-panel__hint">A rename keeps the old name in their username history. Changes reach the game immediately (they reconnect).</p>
            <form method="POST" action="{{ route('panel.admin.users.profile', ['user' => $userId]) }}" class="tomyum-panel__row">
                @csrf
                <label class="tomyum-panel__field">
                    Username
                    <input type="text" name="username" value="{{ $target->username }}" maxlength="15">
                </label>
                <label class="tomyum-panel__field">
                    Email
                    <input type="text" name="email" value="{{ $target->user_email }}">
                </label>
                <label class="tomyum-panel__field" style="flex: 0 1 200px">
                    Country
                    <select name="country">
                        @foreach ($countries as $acronym => $name)
                            <option value="{{ $acronym }}" @selected($acronym === $target->country_acronym)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="tomyum-panel__button" type="submit">Save</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-key"></i>Password</h2>
            <p class="tomyum-panel__hint">Sets a new password for the website and the game. Give it to them privately; they can change it in their settings.</p>
            <form method="POST" action="{{ route('panel.admin.users.password', ['user' => $userId]) }}" class="tomyum-panel__row" autocomplete="off">
                @csrf
                <label class="tomyum-panel__field">
                    New password (at least 8 characters)
                    <input type="text" name="password" minlength="8" required autocomplete="new-password">
                </label>
                <button class="tomyum-panel__button" type="submit">Set password</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-users"></i>Groups</h2>
            <p class="tomyum-panel__hint">
                Admin, GMT and developer groups also give in-game staff powers. Beatmap nominator groups are per game mode.
                @if ($isSelf)
                    You can't remove your own admin group.
                @endif
            </p>
            <form method="POST" action="{{ route('panel.admin.users.groups', ['user' => $userId]) }}">
                @csrf
                <table class="tomyum-panel__table">
                    @foreach ($groups as $group)
                        @php
                            $membership = $memberships[$group->getKey()] ?? null;
                            $modes = $membership?->playmodes ?? [];
                        @endphp
                        <tr>
                            <td style="width: 30px">
                                <input type="checkbox" name="groups[]" value="{{ $group->identifier }}" id="group-{{ $group->identifier }}" @checked($membership !== null)>
                            </td>
                            <td>
                                <label for="group-{{ $group->identifier }}">
                                    @if ($group->colour)
                                        <span class="tomyum-panel__badge" style="background: {{ $group->colour }}; color: #000">{{ $group->short_name }}</span>
                                    @endif
                                    {{ $group->group_name }}
                                </label>
                                @if ($group->identifier === 'no_profile')
                                    <span class="tomyum-panel__hint">(hides their profile)</span>
                                @endif
                            </td>
                            <td>
                                @if ($group->has_playmodes)
                                    @foreach ($playmodes as $mode => $label)
                                        <label style="margin-right: 10px; white-space: nowrap">
                                            <input type="checkbox" name="playmodes[{{ $group->identifier }}][]" value="{{ $mode }}" @checked(in_array($mode, $modes, true))>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
                <p><button class="tomyum-panel__button" type="submit">Save groups</button></p>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-heart"></i>Supporter</h2>
            <p>
                @if ($target->isSupporter())
                    Supporter until {{ $target->osu_subscriptionexpiry?->toFormattedDateString() ?? '—' }} (level {{ $target->supportLevel() }}, {{ $target->support_length ?? 0 }} months total).
                @else
                    Not a supporter ({{ $target->support_length ?? 0 }} months in the past).
                @endif
            </p>
            <div class="tomyum-panel__row">
                <form method="POST" action="{{ route('panel.admin.users.supporter', ['user' => $userId]) }}" class="tomyum-panel__row" style="flex: 1">
                    @csrf
                    <input type="hidden" name="action" value="grant">
                    <label class="tomyum-panel__field" style="flex: 0 1 160px">
                        Months to add
                        <input type="number" name="months" min="1" max="120" value="1" required>
                    </label>
                    <button class="tomyum-panel__button" type="submit">Give supporter</button>
                </form>
                @if ($target->isSupporter())
                    <form method="POST" action="{{ route('panel.admin.users.supporter', ['user' => $userId]) }}" onsubmit="return confirm('Remove supporter now?')">
                        @csrf
                        <input type="hidden" name="action" value="remove">
                        <button class="tomyum-panel__button tomyum-panel__button--danger" type="submit">Remove supporter</button>
                    </form>
                @endif
            </div>
            <p class="tomyum-panel__hint">Supporter levels come from the total time: level 2 after 1 year, level 3 after 5 years.</p>
        </div>

        @if (!$isSelf && !$target->isAdmin() && !$target->trashed())
            <div class="tomyum-panel__card tomyum-panel__card--danger">
                <h2><i class="fas fa-trash"></i>Delete account</h2>
                <p class="tomyum-panel__hint">
                    Renames it to DeletedUser_{{ $userId }}, removes the email and all groups, and bans it from the game.
                    Scores stay on record but are hidden. This can't be undone from the panel.
                </p>
                <form method="POST" action="{{ route('panel.admin.users.delete', ['user' => $userId]) }}" class="tomyum-panel__row"
                    onsubmit="return confirm('Delete this account?')">
                    @csrf
                    <label class="tomyum-panel__field">
                        Type "{{ $target->username }}" to confirm
                        <input type="text" name="confirm" required autocomplete="off">
                    </label>
                    <button class="tomyum-panel__button tomyum-panel__button--danger" type="submit">Delete</button>
                </form>
            </div>
        @endif

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-history"></i>Staff actions</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                @include('panel._staff_log')
            @endif
        </div>
    </div>
@endsection
