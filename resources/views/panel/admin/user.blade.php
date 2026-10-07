{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $userId = $target->getKey();
    $targets = [$userId => $target->username];
    $sections = [
        'standing' => ['fas fa-balance-scale', 'Standing'],
        'access' => ['fas fa-lock', 'Lock'],
        'devices' => ['fas fa-desktop', 'Devices'],
        'logins' => ['fas fa-sign-in-alt', 'Logins'],
        'connections' => ['fas fa-link', 'Links'],
        'content' => ['fas fa-eraser', 'Content'],
        'rollback' => ['fas fa-undo', 'Rollback'],
        'names' => ['fas fa-id-badge', 'Username'],
        'profile' => ['fas fa-user', 'Profile'],
        'groups' => ['fas fa-users', 'Groups'],
        'supporter' => ['fas fa-heart', 'Supporter'],
        'log' => ['fas fa-history', 'Log'],
    ];
    $rank = $stats?->globalRank();
@endphp
@extends('master', ['titlePrepend' => $target->username])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['url' => route('panel.admin.users.index'), 'title' => 'Accounts'],
        ['title' => $target->username],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__hero">
            <div class="js-react u-contents" data-react="user-card" data-user="{{ json_encode($userCard) }}"></div>

            <div class="tomyum-panel__card">
                <div class="tomyum-panel__card-head">
                    <h2><i class="fas fa-id-card"></i>{{ $target->username }} <span class="tomyum-panel__muted">#{{ $userId }}</span></h2>
                    <div class="tomyum-panel__row" style="gap: 8px">
                        <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('users.show', ['user' => $userId]) }}">
                            <i class="fas fa-external-link-alt"></i>Profile
                        </a>
                        @if (App\Http\Controllers\Panel\Controller::canUseGmt(Auth::user()))
                            <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('panel.gmt.users.show', ['user' => $userId]) }}">
                                <i class="fas fa-gavel"></i>GMT panel
                            </a>
                        @endif
                    </div>
                </div>
                <div class="tomyum-panel__facts">
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Joined</span>
                        <span class="tomyum-panel__fact-value">{{ $target->user_regdate?->toFormattedDateString() ?? '—' }}</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Last seen</span>
                        <span class="tomyum-panel__fact-value">{!! $target->user_lastvisit ? timeago($target->user_lastvisit) : 'never' !!}</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">{{ $playmodes[$target->playmode] ?? 'osu!' }} rank</span>
                        <span class="tomyum-panel__fact-value">{{ $rank === null ? '—' : '#'.number_format($rank) }}</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Performance</span>
                        <span class="tomyum-panel__fact-value">{{ number_format($stats?->rank_score ?? 0) }}pp</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Play count</span>
                        <span class="tomyum-panel__fact-value">{{ number_format($stats?->playcount ?? 0) }}</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Email</span>
                        <span class="tomyum-panel__fact-value" title="{{ $target->user_email }}">{{ $target->user_email ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- The sections, like a profile's (osu-web's page-mode tabs); the one being read is underlined. --}}
        <div class="sticky-toolbar tomyum-panel__sections js-tomyum-sections">
            <div class="page-mode page-mode--profile-page-extra">
                @foreach ($sections as $id => [, $label])
                    <a class="page-mode__item" href="#{{ $id }}" data-section="{{ $id }}">
                        <span class="page-mode-link page-mode-link--profile-page">
                            <span class="fake-bold" data-content="{{ $label }}">{{ $label }}</span>
                            <span class="page-mode-link__stripe"></span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        @if ($target->isBot() && !App\Libraries\TomYumBots::isBanchoBot($target))
            <div class="tomyum-panel__card tomyum-panel__row" style="align-items: center">
                <i class="fas fa-robot"></i>
                <span style="flex: 1">This is a bot account (IRC and the website only).</span>
                <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('panel.bots.show', ['bot' => $userId]) }}">Bot settings</a>
            </div>
        @endif

        <div class="tomyum-panel__card tomyum-panel__row" style="align-items: center">
            <i class="fas fa-medal"></i>
            <span style="flex: 1">Kudosu: {{ number_format($target->osu_kudostotal) }} earned, {{ number_format($target->osu_kudosavailable) }} available.</span>
            <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('panel.kudosu.index', ['user' => $userId]) }}">Change kudosu</a>
        </div>

        @include('panel.admin._moderation')

        <div class="tomyum-panel__card" id="profile">
            <h2><i class="fas fa-user"></i>Profile</h2>
            <p class="tomyum-panel__hint">A rename keeps the old name in their username history. Changes reach the game immediately (they reconnect).</p>
            <form method="POST" action="{{ route('panel.admin.users.profile', ['user' => $userId]) }}" class="tomyum-panel__row">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Username</span>
                    <input class="input-text" type="text" name="username" value="{{ $target->username }}" maxlength="15">
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Email</span>
                    <input class="input-text" type="text" name="email" value="{{ $target->user_email }}">
                </label>
                <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 200px">
                    <span class="input-container__label">Country</span>
                    <select class="input-text" name="country">
                        @foreach ($countries as $acronym => $name)
                            <option value="{{ $acronym }}" @selected($acronym === $target->country_acronym)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
            </form>

            <h3>Password</h3>
            <p class="tomyum-panel__hint">Sets a new password for the website and the game. Give it to them privately; they can change it in their settings.</p>
            <form method="POST" action="{{ route('panel.admin.users.password', ['user' => $userId]) }}" class="tomyum-panel__row" autocomplete="off">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">New password (at least 8 characters)</span>
                    <input class="input-text" type="text" name="password" minlength="8" required autocomplete="new-password">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-key"></i>Set password</button>
            </form>
        </div>

        <div class="tomyum-panel__columns">
            <div class="tomyum-panel__card" id="groups">
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
                                <td class="tomyum-panel__nowrap">
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
                                        {{-- Each mode as osu-web's ruleset icon, its name in the tooltip: four fit on one line. --}}
                                        <span class="tomyum-panel__modes">
                                            @foreach ($playmodes as $mode => $label)
                                                <label class="tomyum-panel__check" style="min-height: 0" title="{{ $label }}">
                                                    <input type="checkbox" name="playmodes[{{ $group->identifier }}][]" value="{{ $mode }}" @checked(in_array($mode, $modes, true))>
                                                    <i class="fal fa-extra-mode-{{ $mode }}"></i>
                                                </label>
                                            @endforeach
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <p><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save groups</button></p>
                </form>
            </div>

            <div class="tomyum-panel__card" id="supporter">
                <h2><i class="fas fa-heart"></i>Supporter</h2>
                <p>
                    @if ($target->isSupporter())
                        <span class="tomyum-panel__pill tomyum-panel__pill--pink">Supporter</span>
                        until {{ $target->osu_subscriptionexpiry?->toFormattedDateString() ?? '—' }} (level {{ $target->supportLevel() }}, {{ $target->support_length ?? 0 }} months total).
                    @else
                        Not a supporter ({{ $target->support_length ?? 0 }} months in the past).
                    @endif
                </p>
                <div class="tomyum-panel__row">
                    <form method="POST" action="{{ route('panel.admin.users.supporter', ['user' => $userId]) }}" class="tomyum-panel__row" style="flex: 1">
                        @csrf
                        <input type="hidden" name="action" value="grant">
                        <label class="input-container tomyum-panel__field" style="flex: 0 1 160px">
                            <span class="input-container__label">Months to add</span>
                            <input class="input-text" type="number" name="months" min="1" max="120" value="1" required>
                        </label>
                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-heart"></i>Give supporter</button>
                    </form>
                    @if ($target->isSupporter())
                        <form method="POST" action="{{ route('panel.admin.users.supporter', ['user' => $userId]) }}" onsubmit="return confirm('Remove supporter now?')">
                            @csrf
                            <input type="hidden" name="action" value="remove">
                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove supporter</button>
                        </form>
                    @endif
                </div>
                <p class="tomyum-panel__hint">Supporter levels come from the total time: level 2 after 1 year, level 3 after 5 years.</p>
            </div>
        </div>

        @if (!$isSelf && !$target->isAdmin() && !$target->trashed())
            <div class="tomyum-panel__card tomyum-panel__card--danger" id="delete">
                <h2><i class="fas fa-trash"></i>Delete account</h2>
                <p class="tomyum-panel__hint">
                    Renames it to DeletedUser_{{ $userId }}, removes the email and all groups, and bans it from the game.
                    Their scores stay on beatmap leaderboards (deleted isn't restricted), but they leave the rankings and can't log in. This can't be undone from the panel.
                </p>
                <form method="POST" action="{{ route('panel.admin.users.delete', ['user' => $userId]) }}" class="tomyum-panel__row"
                    onsubmit="return confirm('Delete this account?')">
                    @csrf
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Type "{{ $target->username }}" to confirm</span>
                        <input class="input-text" type="text" name="confirm" required autocomplete="off">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-trash"></i>Delete</button>
                </form>
            </div>
        @endif

        <div class="tomyum-panel__card" id="log">
            <h2><i class="fas fa-history"></i>Staff actions</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                @include('panel._staff_log')
            @endif
        </div>
    </div>
@endsection

@section('script')
    @parent
    <script>
        // The section being read gets the tab's underline (like a profile's tabs while scrolling).
        (() => {
            const bar = document.querySelector('.js-tomyum-sections');
            if (bar == null || !('IntersectionObserver' in window)) return;

            const links = new Map([...bar.querySelectorAll('[data-section]')].map((link) => [link.dataset.section, link.querySelector('.page-mode-link')]));
            const visible = new Set();
            const update = () => {
                const current = [...links.keys()].find((id) => visible.has(id));
                links.forEach((link, id) => link.classList.toggle('page-mode-link--is-active', id === current));
            };
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => entry.isIntersecting ? visible.add(entry.target.id) : visible.delete(entry.target.id));
                update();
            }, { rootMargin: '-120px 0px -55% 0px' });
            links.forEach((_link, id) => {
                const section = document.getElementById(id);
                if (section != null) observer.observe(section);
            });
        })();
    </script>
@endsection
