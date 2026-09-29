{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Daily challenge'])

@php
    // Mods come as arrays or objects (JSON columns), and the list itself may be either.
    $mods = function ($list) { $list = array_values((array) ($list ?? [])); return count($list) === 0 ? '–' : implode(' ', array_map(fn ($mod) => (is_array($mod) ? $mod['acronym'] ?? null : $mod->acronym ?? null) ?? '?', $list)); };
    $mapName = fn ($beatmap) => $beatmap === null ? 'a map that is gone' : "{$beatmap->beatmapset->artist} - {$beatmap->beatmapset->title} [{$beatmap->version}]";
@endphp

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Daily challenge'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2>Today</h2>
            @if ($current === null)
                <p class="tomyum-panel__hint">No daily challenge is open right now. The next one opens at 00:05 UTC.</p>
            @else
                @php($item = $current->playlist->first())
                <p>
                    <strong>{{ $current->name }}</strong>:
                    <a href="{{ route('beatmaps.show', ['beatmap' => $item?->beatmap_id]) }}">{{ $mapName($item?->beatmap) }}</a>
                    ({{ $rulesets[$item?->ruleset_id] ?? '?' }}), open until {{ $current->ends_at?->toDateTimeString() }} UTC.
                </p>
                <p class="tomyum-panel__hint">Required mods: {{ $mods($item?->required_mods) }}. Allowed: {{ $mods($item?->allowed_mods) }}.</p>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2>Coming up</h2>
            <p class="tomyum-panel__hint">
                osu-web opens a room from the first map every day at 00:05 UTC. TomYum keeps a week queued with random
                ranked maps; a map you pick takes the place of a random one.
            </p>
            @if (count($queue) === 0)
                <p class="tomyum-panel__hint">Nothing is queued (TomYum fills the queue within the hour).</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Day</th><th>Map</th><th>Ruleset</th><th>Required</th><th>Allowed</th><th></th></tr>
                    @foreach ($queue as $index => $entry)
                        <tr>
                            <td>{{ $firstDay->addDays($index)->format('D j M') }}</td>
                            <td>
                                <a href="{{ route('beatmaps.show', ['beatmap' => $entry->beatmap_id]) }}">{{ $mapName($entry->beatmap) }}</a>
                                @if ($entry->beatmap !== null)
                                    <span class="tomyum-panel__hint">★{{ number_format($entry->beatmap->difficultyrating, 2) }}</span>
                                @endif
                                @if ($entry->description)
                                    <div class="tomyum-panel__hint">{{ $entry->description }}</div>
                                @endif
                            </td>
                            <td>{{ $rulesets[$entry->ruleset_id] ?? '?' }}</td>
                            <td>{{ $mods($entry->required_mods) }}</td>
                            <td>{{ $mods($entry->allowed_mods) }}</td>
                            <td style="white-space: nowrap">
                                <form method="POST" action="{{ route('panel.admin.daily-challenge.move', ['item' => $entry->getKey()]) }}" style="display: inline">
                                    @csrf
                                    <button class="tomyum-panel__button" name="direction" value="up" type="submit" @if ($index === 0) disabled @endif title="Earlier">▲</button>
                                    <button class="tomyum-panel__button" name="direction" value="down" type="submit" @if ($index === count($queue) - 1) disabled @endif title="Later">▼</button>
                                </form>
                                <form method="POST" action="{{ route('panel.admin.daily-challenge.destroy', ['item' => $entry->getKey()]) }}" style="display: inline">
                                    @csrf
                                    <button class="tomyum-panel__button tomyum-panel__button--danger" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2>Pick a map</h2>
            <form method="POST" action="{{ route('panel.admin.daily-challenge.store') }}" class="tomyum-panel">
                @csrf
                <div class="tomyum-panel__row">
                    <label class="tomyum-panel__field">
                        Beatmap link or id (a difficulty, with a leaderboard)
                        <input type="text" name="beatmap" required placeholder="https://osu.ppy.sh/beatmaps/129891">
                    </label>
                    <label class="tomyum-panel__field" style="flex: 0 1 160px">
                        Ruleset
                        <select name="ruleset">
                            <option value="">the map's own</option>
                            @foreach ($rulesets as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="tomyum-panel__field" style="flex: 0 1 160px">
                        Goes
                        <select name="position">
                            <option value="next">next (tomorrow)</option>
                            <option value="last">last</option>
                        </select>
                    </label>
                </div>
                <div class="tomyum-panel__row">
                    <label class="tomyum-panel__field">
                        Required mods (e.g. HD, or DT for a speed day)
                        <input type="text" name="required_mods" placeholder="none">
                    </label>
                    <label class="tomyum-panel__field">
                        Allowed mods (left empty: {{ $defaultAllowed[0] }}; mania {{ $defaultAllowed[3] }})
                        <input type="text" name="allowed_mods" placeholder="the usual, no rate changes">
                    </label>
                </div>
                <label class="tomyum-panel__field">
                    Description (optional, shown in lazer)
                    <input type="text" name="description" maxlength="255">
                </label>
                <div><button class="tomyum-panel__button" type="submit">Queue it</button></div>
            </form>
        </div>
    </div>
@endsection
