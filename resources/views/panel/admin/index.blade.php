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
                ['panel.admin.assets.index', 'fa-photo-video', 'assets host', 'Assets', 'Badge, banner and medal images'],
                ['panel.admin.titles.index', 'fa-id-badge', 'profiles', 'Titles', "The line under a player's name"],
                ['panel.admin.tournament-banners.index', 'fa-trophy', 'profiles', 'Tournament banners', 'Banners under players\' headers'],
                ['panel.admin.news.index', 'fa-newspaper', 'website & lazer', 'News', 'Write and edit posts'],
                ['panel.admin.seasonal-backgrounds.index', 'fa-image', 'lazer & stable', 'Seasonal backgrounds', "Menu backgrounds and the season's end"],
                ['panel.admin.artists.index', 'fa-music', 'website', 'Featured artists', 'Artists, albums and tracks'],
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
                        <input class="input-text" type="text" name="message" maxlength="500" placeholder="{{ $GLOBALS['cfg']['tomyum']['server_name'].' is under maintenance. You\'ll be reconnected automatically when it\'s over.' }}">
                    </label>
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 170px">
                        <span class="input-container__label">Lock</span>
                        <select class="input-text" name="delay">
                            <option value="0">in 10 seconds (countdown)</option>
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

        @php
            $ppReworks = App\Libraries\TomYumPpRework::status();
        @endphp
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-calculator"></i>pp rework</h2>
            <p class="tomyum-panel__hint">
                Works out every score's pp again in the modes you pick, as osu! does when its pp changes. While it runs, those modes'
                rankings stay as they were and new scores' pp waits; then everything switches over at once. Vanilla modes then wait
                for the website's score processor too.
            </p>
            @foreach ($ppReworks['active'] ?? [] as $rework)
                <div class="tomyum-panel__item">
                    <strong>#{{ $rework['id'] }}: {{ App\Libraries\TomYumPpRework::modeNames($rework['modes'] ?? []) }}</strong>
                    @if (present($rework['reason'] ?? null)) <span class="tomyum-panel__muted">· {{ $rework['reason'] }}</span> @endif
                    @if (present($rework['toVersion'] ?? null))
                        <span class="tomyum-panel__muted">· {{ $rework['library'] }} {{ $rework['fromVersion'] }} → {{ $rework['toVersion'] }}</span>
                    @endif
                    <div class="tomyum-panel__progress" style="margin: 6px 0"><div style="width: {{ App\Libraries\TomYumPpRework::percent($rework) }}%"></div></div>
                    <div class="tomyum-panel__muted">
                        {{ App\Libraries\TomYumPpRework::progress($rework) }} ·
                        {{ i18n_number_format(max(0, ($rework['total'] ?? 0) - ($rework['done'] ?? 0))) }} score(s) left to recalculate ·
                        started {!! timeago(Carbon\Carbon::parse($rework['startedAt'])) !!}
                    </div>
                    <form method="POST" action="{{ route('panel.admin.pp-rework') }}" onsubmit="return confirm('Stop this rework? The pp stays as it was.')">
                        @csrf
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="id" value="{{ $rework['id'] }}">
                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit" style="margin-top: 6px">Cancel</button>
                    </form>
                </div>
            @endforeach

            @php
                $ppLibraries = App\Libraries\TomYumPpRework::libraries();
                $ppStatus = $ppLibraries['status'] ?? null;
                $ppJob = $ppLibraries['job'] ?? null;
                $shortRef = fn (?string $ref) => $ref === null ? '' : substr($ref, 0, 7);
            @endphp
            <h3>pp libraries</h3>
            @if ($ppStatus === null)
                <p class="tomyum-panel__muted">The pp updater hasn't reported yet (it runs on the server itself: deploy/pp-updater).</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Library</th><th>Running</th><th>Newest</th><th></th></tr>
                    @foreach (['vanilla' => 'Vanilla', 'akatsuki' => 'Relax / Autopilot'] as $key => $label)
                        @php $library = $ppStatus[$key] ?? []; @endphp
                        <tr>
                            <td>
                                <strong>{{ $label }}</strong><br>
                                <span class="tomyum-panel__muted">{{ $library['library'] ?? '' }}</span>
                            </td>
                            <td>
                                {{ $library['current'] ?? '?' }}
                                @if ($key === 'akatsuki') <span class="tomyum-panel__muted">({{ $shortRef($library['commit'] ?? null) }})</span>
                                @else <span class="tomyum-panel__muted">(processor {{ $shortRef($library['processorRef'] ?? null) }})</span> @endif
                            </td>
                            <td>
                                @if (isset($library['error']))
                                    <span class="tomyum-panel__pill tomyum-panel__pill--bad">couldn't check</span>
                                @else
                                    {{ $library['latest'] ?? '?' }}
                                    @if ($key === 'akatsuki') <span class="tomyum-panel__muted">({{ $shortRef($library['latestCommit'] ?? null) }})</span>
                                    @else <span class="tomyum-panel__muted">(processor {{ $shortRef($library['latestProcessorRef'] ?? null) }})</span> @endif
                                    @if ($key === 'vanilla' && isset($library['osuRelease']) && $library['osuRelease'] !== ($library['latest'] ?? null))
                                        <br><span class="tomyum-panel__muted">osu! released {{ $library['osuRelease'] }}; ppy's score processor isn't on it yet (vanilla pp must match it).</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if ($library['available'] ?? false)
                                    <form method="POST" action="{{ route('panel.admin.pp-library') }}"
                                        onsubmit="return confirm({{ json_encode('Update the '.$label.' pp library? The server rebuilds with it, then reworks '.App\Libraries\TomYumPpRework::modeNames($library['modes'] ?? []).': those rankings pause and new pp waits until it is done.') }})">
                                        @csrf
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="library" value="{{ $key }}">
                                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-download"></i>Update</button>
                                    </form>
                                @elseif (!isset($library['error']))
                                    <span class="tomyum-panel__pill tomyum-panel__pill--good">up to date</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
                <form method="POST" action="{{ route('panel.admin.pp-library') }}" class="tomyum-panel__row" style="align-items: center; margin-top: 6px">
                    @csrf
                    <input type="hidden" name="action" value="check">
                    <span class="tomyum-panel__muted">Checked {!! isset($ppStatus['checkedAt']) ? timeago(Carbon\Carbon::parse($ppStatus['checkedAt'])) : 'never' !!} (every 6 hours).</span>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Check now</button>
                </form>
            @endif
            @if ($ppJob !== null)
                <div class="tomyum-panel__item" style="margin-top: 8px">
                    <strong>{{ $ppJob['library'] === 'vanilla' ? 'Vanilla' : 'Relax / Autopilot' }} update:</strong>
                    <span class="{{ class_with_modifiers('tomyum-panel__pill', [($ppJob['state'] ?? '') === 'failed' ? 'bad' : (($ppJob['state'] ?? '') === 'done' ? 'good' : 'info')]) }}">{{ $ppJob['state'] ?? '?' }}</span>
                    <ul class="tomyum-panel__muted" style="margin: 4px 0 0 18px">
                        @foreach (array_slice($ppJob['steps'] ?? [], -6) as $step)
                            <li>{{ $step['text'] }}</li>
                        @endforeach
                    </ul>
                    @if (present($ppJob['error'] ?? null))
                        <pre class="tomyum-panel__muted" style="white-space: pre-wrap">{{ $ppJob['error'] }}</pre>
                    @endif
                </div>
            @endif
            <h3 style="margin-top: 12px">Rework by hand</h3>
            <form method="POST" action="{{ route('panel.admin.pp-rework') }}" onsubmit="return confirm('Start a pp rework? Rankings in these modes pause until it is done.')">
                @csrf
                <input type="hidden" name="action" value="start">
                <div class="tomyum-panel__row" style="flex-wrap: wrap; gap: 4px 12px; margin-bottom: 8px">
                    @foreach (App\Libraries\TomYumPpRework::MODES as $mode => [, , $name])
                        <label class="tomyum-panel__check"><input type="checkbox" name="modes[]" value="{{ $mode }}"> {{ $name }}</label>
                    @endforeach
                </div>
                <label class="input-container">
                    <span class="input-container__label">Reason (shown on the website)</span>
                    <input class="input-text" type="text" name="reason" maxlength="200" placeholder="e.g. Relax now uses Akatsuki's formula">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit" style="margin-top: 8px"><i class="fas fa-play"></i>Start rework</button>
            </form>

            @if (count($ppReworks['recent'] ?? []) > 0)
                <h3 style="margin-top: 12px">Recent</h3>
                <table class="tomyum-panel__table">
                    <tr><th>#</th><th>Modes</th><th>State</th><th>Scores</th><th>Finished</th></tr>
                    @foreach ($ppReworks['recent'] as $rework)
                        <tr>
                            <td>{{ $rework['id'] }}</td>
                            <td>{{ App\Libraries\TomYumPpRework::modeNames($rework['modes'] ?? []) }}</td>
                            <td>{{ $rework['state'] }}</td>
                            <td>{{ i18n_number_format($rework['done'] ?? 0) }}</td>
                            <td>{!! isset($rework['finishedAt']) ? timeago(Carbon\Carbon::parse($rework['finishedAt'])) : '' !!}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
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
