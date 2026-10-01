{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: the Tournament Committee panel's front page, the game's live multiplayer rooms (the ones
    being played first): who's in them and how far along, and per room, abort the map, close the
    room, or say something in its chat as BanchoBot (Panel\TcController).
--}}
@extends('master', ['titlePrepend' => 'TC panel'])

@section('content')
    @php
        $rulesets = ['osu!', 'osu!taiko', 'osu!catch', 'osu!mania'];
        $teamTypes = ['HeadToHead' => 'Head to head', 'TagCoop' => 'Tag co-op', 'TeamVs' => 'Team VS', 'TagTeamVs' => 'Tag team VS'];
        $teamNames = [1 => 'blue', 2 => 'red'];
    @endphp
    @include('panel._header', ['crumbs' => [['url' => route('panel.tc.index'), 'title' => 'TC panel']]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-trophy"></i>Live matches</h2>
                <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.tc.index') }}">Refresh</a>
            </div>
            <p class="tomyum-panel__hint">
                The multiplayer rooms open in the game right now. Abort stops the map being played (or a countdown) and sends its
                players back to the room; Close sends everyone back to the lobby and ends the room. Messages show in the room's chat
                as BanchoBot.
            </p>

            @if ($matches === null)
                <p class="tomyum-panel__empty">The game server couldn't be reached.</p>
            @elseif (count($matches) === 0)
                <p class="tomyum-panel__empty">No rooms are open right now.</p>
            @endif
        </div>

        @foreach ($matches ?? [] as $match)
            <div class="tomyum-panel__card">
                <div class="tomyum-panel__card-head">
                    <h2>
                        <i class="fas fa-users"></i>{{ $match['name'] }}
                        <span class="tomyum-panel__hint">#{{ $match['id'] }}</span>
                    </h2>
                    <span>
                        @if ($match['inProgress'])
                            <span class="tomyum-panel__pill tomyum-panel__pill--good">playing</span>
                        @else
                            <span class="tomyum-panel__pill">waiting</span>
                        @endif
                        @if ($match['hasPassword'])
                            <span class="tomyum-panel__pill tomyum-panel__pill--info"><i class="fas fa-lock"></i> password</span>
                        @endif
                        @if ($match['tournamentWatchers'] > 0)
                            <span class="tomyum-panel__pill tomyum-panel__pill--pink">{{ $match['tournamentWatchers'] }} tournament {{ $match['tournamentWatchers'] === 1 ? 'window' : 'windows' }}</span>
                        @endif
                    </span>
                </div>

                <p class="tomyum-panel__meta">
                    {{ $rulesets[$match['mode']] ?? 'osu!' }} · {{ $teamTypes[$match['teamType']] ?? $match['teamType'] }}
                    · host <a href="{{ route('users.show', ['user' => $match['hostUserId']]) }}">{{ $match['hostUsername'] }}</a>
                    · map
                    @if ($match['mapId'] > 0)
                        <a href="{{ route('beatmaps.show', ['beatmap' => $match['mapId']]) }}">{{ $match['mapName'] }}</a>
                    @else
                        {{ $match['mapName'] }}
                    @endif
                </p>

                @if (count($match['players']) === 0)
                    <p class="tomyum-panel__empty">Nobody's in the room.</p>
                @else
                    <table class="tomyum-panel__table">
                        <thead>
                            <tr><th>Slot</th><th>Player</th><th>State</th><th>Team</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($match['players'] as $player)
                                <tr>
                                    <td class="tomyum-panel__muted">{{ $player['slot'] + 1 }}</td>
                                    <td>
                                        <a href="{{ route('users.show', ['user' => $player['userId']]) }}">{{ $player['username'] }}</a>
                                        @if ($player['userId'] === $match['hostUserId'])
                                            <span class="tomyum-panel__pill">host</span>
                                        @endif
                                    </td>
                                    <td>{{ $player['status'] }}</td>
                                    <td class="tomyum-panel__muted">{{ $teamNames[$player['team']] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <form method="POST" action="{{ route('panel.tc.matches.message', ['match' => $match['id']]) }}" class="tomyum-panel__row" style="margin-top: 12px">
                    @csrf
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Say in the room's chat</span>
                        <input class="input-text" type="text" name="text" maxlength="450" required placeholder="2 minutes to ready up">
                    </label>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Send</button></div>
                </form>

                <div class="tomyum-panel__buttons" style="margin-top: 12px">
                    <form method="POST" action="{{ route('panel.tc.matches.abort', ['match' => $match['id']]) }}">
                        @csrf
                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit" @if (!$match['inProgress']) title="Also stops a countdown" @endif>Abort map</button>
                    </form>
                    <form method="POST" action="{{ route('panel.tc.matches.close', ['match' => $match['id']]) }}"
                        onsubmit="return confirm('Close room #{{ $match['id'] }}? Everyone in it goes back to the lobby.')">
                        @csrf
                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Close room</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
