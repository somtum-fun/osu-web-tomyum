{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Ranked Play'])

@php
    $poolName = fn ($pool) => $rulesets[(int) $pool->ruleset_id].((int) $pool->ruleset_id === 3 ? " {$pool->variant_id}K" : '');
    $autoCount = fn ($pool) => (int) ($automatic[(int) $pool->ruleset_id === 3 ? "3:{$pool->variant_id}" : (int) $pool->ruleset_id] ?? 0);
    $modText = fn ($mods) => $mods === '' ? 'no mods' : implode(' ', array_column(json_decode($mods, true) ?? [], 'acronym'));
@endphp

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Ranked Play'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-info-circle"></i>How it works</h2>
            <p class="tomyum-panel__hint">
                lazer's Ranked Play (1v1, with ratings) puts players who queue in a pool together. (Quick Play, a
                free-for-all of up to the lobby size, uses the same pools but isn't in lazer right now.) The server fills each pool with the ranked featured-artist
                maps of 1-5 minutes in its ruleset by itself; add maps below when a ruleset has few (Ranked Play
                deals {{ \App\Http\Controllers\Panel\MatchmakingController::RANKED_DECK }} per match), or to play
                a map with mods. A match needs enough players queued in the same pool at the same time.
            </p>
        </div>

        @forelse ($pools as $pool)
            @php($maps = $added[$pool->id] ?? collect())
            @php($total = $autoCount($pool) + count($maps))
            <div class="tomyum-panel__card" style="{{ $pool->active ? '' : 'opacity: 0.6' }}">
                <h2>
                    {{ $pool->type === 'ranked_play' ? 'Ranked Play' : 'Quick Play' }} · {{ $poolName($pool) }} · {{ $pool->name }}
                    @unless ($pool->active) <span class="tomyum-panel__hint">(off)</span> @endunless
                </h2>
                <p class="tomyum-panel__hint">
                    {{ $pool->type === 'ranked_play' ? '1v1' : "Lobbies of {$pool->lobby_size}" }} ·
                    {{ $autoCount($pool) }} maps from the server, {{ count($maps) }} with their own entry (added here, or rated from play)
                    @if ($pool->type === 'ranked_play' && $total < \App\Http\Controllers\Panel\MatchmakingController::RANKED_DECK)
                        · <strong>fewer than {{ \App\Http\Controllers\Panel\MatchmakingController::RANKED_DECK }} maps: matches repeat them</strong>
                    @elseif ($total === 0)
                        · <strong>no maps: add some</strong>
                    @endif
                </p>
                <form method="POST" action="{{ route('panel.admin.matchmaking.update', ['pool' => $pool->id]) }}" class="tomyum-panel__row">
                    @csrf
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Name</span>
                        <input class="input-text" type="text" name="name" value="{{ $pool->name }}" maxlength="255">
                    </label>
                    @if ($pool->type !== 'ranked_play')
                        <label class="input-container tomyum-panel__field" style="flex: 0 1 140px">
                            <span class="input-container__label">Lobby size</span>
                            <input class="input-text" type="number" name="lobby_size" min="2" max="16" value="{{ $pool->lobby_size }}">
                        </label>
                    @endif
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 140px">
                        <span class="input-container__label">On</span>
                        <select class="input-text" name="active">
                            <option value="1" @if ($pool->active) selected @endif>on</option>
                            <option value="0" @unless ($pool->active) selected @endunless>off</option>
                        </select>
                    </label>
                    <div style="align-self: flex-end"><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button></div>
                </form>

                @if (count($maps) > 0)
                    <table class="tomyum-panel__table">
                        <tr><th>Map</th><th>Mods</th><th>Rating</th><th>Played</th><th></th></tr>
                        @foreach ($maps as $map)
                            <tr>
                                <td>
                                    <a href="{{ route('beatmaps.show', ['beatmap' => $map->beatmap_id]) }}">{{ $map->artist }} - {{ $map->title }} [{{ $map->version }}]</a>
                                    <span class="tomyum-panel__hint">★{{ number_format($map->difficultyrating, 2) }}</span>
                                </td>
                                <td>@include('panel._mods', ['mods' => $map->mods, 'ruleset' => (int) $pool->ruleset_id])</td>
                                <td>{{ (int) $map->rating }}</td>
                                <td>{{ $map->selection_count }}×</td>
                                <td>
                                    <form method="POST" action="{{ route('panel.admin.matchmaking.remove-beatmap', ['pool' => $pool->id, 'entry' => $map->id]) }}">
                                        @csrf
                                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                @php($auto = $automaticMaps[$pool->id] ?? collect())
                <details style="margin: 10px 0">
                    <summary style="cursor: pointer">The {{ count($auto) }} maps the server picks by itself (ranked featured-artist maps, 1-5 minutes), easiest first</summary>
                    @if (count($auto) === 0)
                        <p class="tomyum-panel__empty">None: add maps below.</p>
                    @else
                        <table class="tomyum-panel__table">
                            <tr><th>Map</th><th>Stars</th><th>Length</th></tr>
                            @foreach ($auto as $map)
                                <tr>
                                    <td><a href="{{ route('beatmaps.show', ['beatmap' => $map->beatmap_id]) }}">{{ $map->artist }} - {{ $map->title }} [{{ $map->version }}]</a></td>
                                    <td>★{{ number_format($map->difficultyrating, 2) }}</td>
                                    <td>{{ gmdate('i:s', (int) $map->hit_length) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </details>

                <form method="POST" action="{{ route('panel.admin.matchmaking.add-beatmap', ['pool' => $pool->id]) }}" class="tomyum-panel__row">
                    @csrf
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Add a map (link or id, ranked or loved, this pool's ruleset)</span>
                        <input class="input-text" type="text" name="beatmap" required placeholder="https://osu.ppy.sh/beatmaps/129891">
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 160px">
                        <span class="input-container__label">Mods (optional)</span>
                        <input class="input-text" type="text" name="mods" placeholder="none">
                    </label>
                    <div style="align-self: flex-end"><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Add</button></div>
                </form>

                <form method="POST" action="{{ route('panel.admin.matchmaking.destroy', ['pool' => $pool->id]) }}"
                    onsubmit="return confirm('Remove this pool? (Only possible before anyone played in it.)')">
                    @csrf
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove pool</button>
                </form>
            </div>
        @empty
            <div class="tomyum-panel__card"><p class="tomyum-panel__hint">No pools yet: Quick Play and Ranked Play have nothing to queue for. Make one below.</p></div>
        @endforelse

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Make a pool</h2>
            <p class="tomyum-panel__hint">One Ranked Play pool per ruleset (per key count for mania) is enough; players pick it in lazer.</p>
            <form method="POST" action="{{ route('panel.admin.matchmaking.store') }}" class="tomyum-panel">
                @csrf
                <div class="tomyum-panel__row">
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 180px">
                        <span class="input-container__label">Type</span>
                        <select class="input-text" name="type">
                            <option value="ranked_play">Ranked Play (1v1)</option>
                            <option value="quick_play">Quick Play (not in lazer right now)</option>
                        </select>
                    </label>
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 180px">
                        <span class="input-container__label">Ruleset</span>
                        <select class="input-text" name="ruleset">
                            @foreach ($rulesets as $id => $name)
                                <option value="{{ $id }}">{{ $name }} ({{ (int) ($automatic[$id] ?? 0) }} maps)</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 140px">
                        <span class="input-container__label">Keys (mania only)</span>
                        <input class="input-text" type="number" name="keys" min="1" max="10" placeholder="4">
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 140px">
                        <span class="input-container__label">Lobby size (Quick Play)</span>
                        <input class="input-text" type="number" name="lobby_size" min="2" max="16" value="4">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Name (shown in lazer)</span>
                        <input class="input-text" type="text" name="name" maxlength="255" placeholder="Quick Play / Ranked Play">
                    </label>
                </div>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Make it</button></div>
            </form>
        </div>
    </div>
@endsection
