{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $actions = ['Idle', 'AFK', 'Playing', 'Editing', 'Modding', 'In multiplayer', 'Watching', 'Unknown', 'Testing', 'Submitting', 'Paused', 'In the lobby', 'Multiplaying', 'osu!direct'];
    $modes = ['osu!', 'osu!taiko', 'osu!catch', 'osu!mania'];
    $userIds = array_filter(array_map(fn ($e) => str_starts_with($e['action'], 'beatmapset.') ? null : $e['targetId'], $entries));
    $targets = App\Http\Controllers\Panel\GmtController::userNames($userIds);
@endphp
@extends('master', ['titlePrepend' => 'Admin panel'])

@section('content')
    @include('panel._header', ['crumbs' => [['url' => route('panel.admin.index'), 'title' => 'Admin panel']]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <p class="tomyum-panel__section-title">Server</p>
        <div class="tomyum-panel__links tomyum-panel__links--stats">
            <div class="tomyum-panel__link js-tomyum-live">
                <span class="tomyum-panel__kicker">Game server</span>
                @if ($server !== null)
                    <span class="tomyum-panel__stat"><span class="tomyum-panel__live-dot"></span>{{ number_format($server['online']) }} online</span>
                    <p class="tomyum-panel__hint">TomYum {{ $server['version'] }} &middot; up {{ Carbon\Carbon::parse($server['startedAt'])->diffForHumans(null, true) }}</p>
                @else
                    <span class="tomyum-panel__stat"><span class="tomyum-panel__live-dot tomyum-panel__live-dot--off"></span>Unreachable</span>
                    <p class="tomyum-panel__hint">The website can't reach the game server.</p>
                @endif
            </div>
            @foreach ($counts as $label => $count)
                <div class="tomyum-panel__link">
                    <span class="tomyum-panel__kicker">{{ $label }}</span>
                    <span class="tomyum-panel__stat">{{ number_format($count) }}</span>
                </div>
            @endforeach
        </div>

        <p class="tomyum-panel__section-title">Tools</p>
        <div class="tomyum-panel__links">
            @foreach ([
                ['panel.admin.daily-challenge.index', 'fa-calendar-day', 'lazer', 'Daily challenge', 'Pick and order the maps'],
                ['panel.admin.matchmaking.index', 'fa-trophy', 'lazer', 'Ranked Play', 'Pools and their maps'],
                ['panel.admin.mods.index', 'fa-sliders-h', 'lazer', 'Ranked mods', 'Which mods give pp and medals'],
                ['panel.admin.badges.index', 'fa-certificate', 'profiles', 'Badges', 'Give and take profile badges'],
                ['panel.admin.titles.index', 'fa-id-badge', 'profiles', 'Titles', "The line under a player's name"],
                ['panel.admin.tournament-banners.index', 'fa-trophy', 'profiles', 'Tournament banners', 'Banners under players\' headers'],
                ['panel.admin.news.index', 'fa-newspaper', 'website & lazer', 'News', 'Write and edit posts'],
                ['panel.admin.seasonal-backgrounds.index', 'fa-image', 'lazer & stable', 'Seasonal backgrounds', "Menu backgrounds and the season's end"],
            ] as [$route, $icon, $kicker, $title, $hint])
                <a class="tomyum-panel__link" href="{{ route($route) }}">
                    <span class="tomyum-panel__link-icon"><i class="fas {{ $icon }}"></i></span>
                    <span class="tomyum-panel__kicker">{{ $kicker }}</span>
                    <h2>{{ $title }}</h2>
                    <p class="tomyum-panel__hint">{{ $hint }}</p>
                </a>
            @endforeach
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-user-cog"></i>Accounts</h2>
            <form method="GET" action="{{ route('panel.admin.users.index') }}" class="tomyum-panel__row">
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Username, user id or email</span>
                    <input class="input-text" type="text" name="q">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Find</button>
            </form>
        </div>

        <div class="tomyum-panel__card {{ ($maintenance['enabled'] ?? false) ? 'tomyum-panel__card--danger' : '' }}" id="maintenance">
            <h2><i class="fas fa-tools"></i>Maintenance</h2>
            @if ($maintenance['enabled'] ?? false)
                <p>
                    <span class="tomyum-panel__badge tomyum-panel__badge--error">{{ ($maintenance['locked'] ?? false) ? 'ON' : 'STARTING' }}</span>
                    @if (!($maintenance['locked'] ?? false) && $maintenance['startsAt'])
                        Locks {!! timeago(Carbon\Carbon::parse($maintenance['startsAt'])) !!}.
                    @else
                        Only staff can log in (stable, lazer, IRC) since {!! $maintenance['since'] ? timeago(Carbon\Carbon::parse($maintenance['since'])) : '—' !!}.
                    @endif
                </p>
                <p class="tomyum-panel__hint">Players see: "{{ $maintenance['notice'] }}". Their game keeps reconnecting and gets back in on its own when you end it.</p>
                <form method="POST" action="{{ route('panel.admin.maintenance') }}">
                    @csrf
                    <input type="hidden" name="action" value="stop">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-play"></i>End maintenance</button>
                </form>
            @else
                <p class="tomyum-panel__hint">
                    Keeps everyone but staff (admins, developers, GMT) out of stable, lazer and IRC. Players are told at once (an in-game
                    alert, and a BanchoBot message in lazer); when the lock starts, stable is disconnected and keeps reconnecting until
                    maintenance ends, then gets back in by itself. Score submission stays open, so plays in progress are kept. The website stays up.
                </p>
                <form method="POST" action="{{ route('panel.admin.maintenance') }}" class="tomyum-panel__row" style="align-items: flex-end"
                    onsubmit="return confirm('Start maintenance? Players will be disconnected.')">
                    @csrf
                    <input type="hidden" name="action" value="start">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Message for players (optional)</span>
                        <input class="input-text" type="text" name="message" maxlength="500" placeholder="{{ 'production is under maintenance. You\'ll be reconnected automatically when it\'s over.' }}">
                    </label>
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 170px">
                        <span class="input-container__label">Lock</span>
                        <select class="input-text" name="delay">
                            <option value="0">now</option>
                            <option value="60">in 1 minute</option>
                            <option value="300" selected>in 5 minutes</option>
                            <option value="600">in 10 minutes</option>
                            <option value="1800">in 30 minutes</option>
                        </select>
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-tools"></i>Start maintenance</button>
                </form>
            @endif
        </div>

        <div class="tomyum-panel__card" id="alert">
            <h2><i class="fas fa-bullhorn"></i>In-game alert</h2>
            <p class="tomyum-panel__hint">A notification to everyone online in stable (osu!'s yellow pop-up); lazer players get it as a BanchoBot message.</p>
            <form method="POST" action="{{ route('panel.admin.alert') }}" class="tomyum-panel__row" style="align-items: flex-end">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Message</span>
                    <input class="input-text" type="text" name="message" maxlength="500" required>
                </label>
                <label class="tomyum-panel__check"><input type="checkbox" name="lazer" value="1" checked> Also lazer</label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-bullhorn"></i>Send alert</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-calculator"></i>Performance points</h2>
            <p class="tomyum-panel__hint">
                After a pp update: recalculates every score's pp, the players' stats and rankings, in-game and here.
                Takes a while on a big server.
            </p>
            <form method="POST" action="{{ route('panel.admin.recalculate') }}">
                @csrf
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Recalculate all pp</button>
            </form>
        </div>

        <div class="tomyum-panel__card js-tomyum-live">
            <h2><i class="fas fa-signal"></i>Online now</h2>
            @if (count($online) === 0)
                <p class="tomyum-panel__empty">Nobody is online.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Player</th><th>Mode</th><th>Doing</th><th>Last seen</th></tr>
                    @foreach ($online as $player)
                        <tr>
                            <td><a href="{{ route('panel.admin.users.show', ['user' => $player['userId']]) }}">{{ $player['username'] }}</a></td>
                            <td class="tomyum-panel__nowrap">{{ $modes[$player['mode'] % 4] ?? $player['mode'] }}</td>
                            <td>
                                {{ $actions[$player['action']] ?? 'Unknown' }}
                                @if ($player['actionInfo'] !== '')
                                    &middot;
                                    @if ($player['beatmapId'] > 0)
                                        <a href="{{ route('beatmaps.show', ['beatmap' => $player['beatmapId']]) }}">{{ $player['actionInfo'] }}</a>
                                    @else
                                        {{ $player['actionInfo'] }}
                                    @endif
                                @endif
                            </td>
                            <td class="tomyum-panel__nowrap tomyum-panel__muted">{!! timeago(Carbon\Carbon::parse($player['lastActivity'])) !!}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-history"></i>Recent staff actions</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">Nothing yet.</p>
            @else
                @include('panel._staff_log')
            @endif
        </div>
    </div>
    {{-- The online count and list follow along without reloading (the account search keeps its text). --}}
    <script>
        if (window.tomyumLiveRefresh == null) {
            const refresh = async function () {
                const path = {!! json_encode(parse_url(route('panel.admin.index'), PHP_URL_PATH)) !!};
                const live = document.querySelectorAll('.js-tomyum-live');
                if (location.pathname !== path || live.length === 0 || document.hidden) {
                    return;
                }
                try {
                    const response = await fetch(location.href, { credentials: 'same-origin', cache: 'no-store' });
                    if (!response.ok) {
                        return;
                    }
                    const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').querySelectorAll('.js-tomyum-live');
                    live.forEach(function (element, i) {
                        if (fresh[i] != null) {
                            element.innerHTML = fresh[i].innerHTML;
                        }
                    });
                } catch (e) {
                    // Offline for a moment: the next tick tries again.
                }
            };
            window.tomyumLiveRefresh = setInterval(refresh, 10000);
            // It pauses while the tab is in the background: catch up as soon as it's back.
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    refresh();
                }
            });
        }
    </script>
@endsection
