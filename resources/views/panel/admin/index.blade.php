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
        <div class="tomyum-panel__links">
            <div class="tomyum-panel__link">
                <p class="tomyum-panel__hint" style="margin: 0">Game server</p>
                @if ($server !== null)
                    <h2 style="margin: 4px 0">{{ $server['online'] }} online</h2>
                    <p class="tomyum-panel__hint" style="margin: 0">
                        TomYum {{ $server['version'] }} &middot; up {{ Carbon\Carbon::parse($server['startedAt'])->diffForHumans(null, true) }}
                    </p>
                @else
                    <h2 style="margin: 4px 0">Unreachable</h2>
                @endif
            </div>
            @foreach ($counts as $label => $count)
                <div class="tomyum-panel__link">
                    <p class="tomyum-panel__hint" style="margin: 0">{{ $label }}</p>
                    <h2 style="margin: 4px 0">{{ number_format($count) }}</h2>
                </div>
            @endforeach
        </div>

        <div class="tomyum-panel__card">
            <h2>Accounts</h2>
            <form method="GET" action="{{ route('panel.admin.users.index') }}" class="tomyum-panel__row">
                <label class="tomyum-panel__field">
                    Username, user id or email
                    <input type="text" name="q">
                </label>
                <button class="tomyum-panel__button" type="submit">Find</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2>Online now</h2>
            @if (count($online) === 0)
                <p class="tomyum-panel__hint">Nobody is online.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Player</th><th>Mode</th><th>Doing</th><th>Last seen</th></tr>
                    @foreach ($online as $player)
                        <tr>
                            <td><a href="{{ route('panel.admin.users.show', ['user' => $player['userId']]) }}">{{ $player['username'] }}</a></td>
                            <td>{{ $modes[$player['mode'] % 4] ?? $player['mode'] }}</td>
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
                            <td>{!! timeago(Carbon\Carbon::parse($player['lastActivity'])) !!}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2>Recent staff actions</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__hint">Nothing yet.</p>
            @else
                @include('panel._staff_log')
            @endif
        </div>
    </div>
@endsection
