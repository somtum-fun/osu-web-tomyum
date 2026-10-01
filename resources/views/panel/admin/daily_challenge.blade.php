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
        @php
            $daily = App\Http\Controllers\Panel\DailyChallengeController::class;

            $cover = fn ($beatmap) => $beatmap?->beatmapset?->coverURL('card');
            // A star rating in osu!'s colours (light text on the dark end, like osu-web's badges).
            $stars = fn (float $rating) => sprintf(
                '<span class="tomyum-stars" style="--colour: %s; --text: %s"><i class="fas fa-star"></i>%s</span>',
                $daily::starColour($rating), $rating >= 6.5 ? '#fff' : 'hsl(var(--hsl-b6))', number_format($rating, 2),
            );
        @endphp

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-calendar-day"></i>Today</h2>
            @if ($current === null)
                <p class="tomyum-panel__hint">No daily challenge is open right now. The next one opens at 00:05 UTC.</p>
            @else
                @php
                    $item = $current->playlist->first();
                @endphp
                <a class="tomyum-daily-today" href="{{ route('beatmaps.show', ['beatmap' => $item?->beatmap_id]) }}"
                    style="--cover: url('{{ $cover($item?->beatmap) }}')">
                    <span class="tomyum-daily-today__name">{{ $current->name }}</span>
                    <span class="tomyum-daily-today__map">{{ $mapName($item?->beatmap) }}</span>
                    <span class="tomyum-daily-today__meta">
                        @if ($item?->beatmap !== null)
                            {!! $stars($item->beatmap->difficultyrating) !!}
                        @endif
                        {{ $rulesets[$item?->ruleset_id] ?? '?' }} · open until {{ $current->ends_at?->format('H:i') }} UTC
                    </span>
                </a>
                <p class="tomyum-panel__hint" style="margin-bottom: 0">Required mods: {{ $mods($item?->required_mods) }}. Allowed: {{ $mods($item?->allowed_mods) }}.</p>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-list-ol"></i>Coming up</h2>
            <p class="tomyum-panel__hint">
                osu-web opens a room from the first map every day at 00:05 UTC. TomYum keeps a week queued with random
                ranked maps from each day's star range (osu!'s week: easy on Thursday, hard on Wednesday); a map you
                pick takes the place of a random one.
            </p>
            @if (count($queue) === 0)
                <p class="tomyum-panel__empty">Nothing is queued (TomYum fills the queue within the hour).</p>
            @else
                <div class="tomyum-daily-week">
                    @foreach ($queue as $index => $entry)
                        @php
                            $day = $firstDay->addDays($index);
                            [$min, $max] = $daily::starsFor($day);
                            $rating = $entry->beatmap?->difficultyrating;
                            $fits = $rating === null || ($rating >= $min && $rating <= $max);
                        @endphp
                        <div class="tomyum-daily-day" style="--tier: {{ $daily::starColour(($min + $max) / 2) }}; --cover: url('{{ $cover($entry->beatmap) }}')">
                            <div class="tomyum-daily-day__tier"><i class="fas fa-star"></i>{{ rtrim(rtrim(number_format($min, 1), '0'), '.') }}–{{ rtrim(rtrim(number_format($max, 1), '0'), '.') }}</div>
                            <div class="tomyum-daily-day__body">
                                <div class="tomyum-daily-day__weekday">{{ $day->format('l') }} <span>{{ $day->format('j M') }}</span></div>
                                <a class="tomyum-daily-day__map" href="{{ route('beatmaps.show', ['beatmap' => $entry->beatmap_id]) }}">{{ $mapName($entry->beatmap) }}</a>
                                <div class="tomyum-daily-day__meta">
                                    @if ($rating !== null)
                                        {!! $stars($rating) !!}
                                    @endif
                                    <span>{{ $rulesets[$entry->ruleset_id] ?? '?' }}</span>
                                    @if (!$fits)
                                        <span class="tomyum-daily-day__off" title="Outside {{ $day->format('l') }}'s {{ $min }}–{{ $max }}★">
                                            <i class="fas fa-exclamation-triangle"></i>off range
                                        </span>
                                    @endif
                                </div>
                                <div class="tomyum-daily-day__mods">
                                    @if ($mods($entry->required_mods) !== '–')
                                        <span>Required {{ $mods($entry->required_mods) }}</span>
                                    @endif
                                    <span>Allowed {{ $mods($entry->allowed_mods) }}</span>
                                </div>
                                @if ($entry->description)
                                    <div class="tomyum-panel__hint" style="margin: 0">{{ $entry->description }}</div>
                                @endif
                                <div class="tomyum-daily-day__actions">
                                    <form method="POST" action="{{ route('panel.admin.daily-challenge.move', ['item' => $entry->getKey()]) }}">
                                        @csrf
                                        <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" name="direction" value="up" type="submit" @disabled($index === 0) title="A day earlier"><i class="fas fa-arrow-left"></i></button>
                                        <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" name="direction" value="down" type="submit" @disabled($index === count($queue) - 1) title="A day later"><i class="fas fa-arrow-right"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('panel.admin.daily-challenge.destroy', ['item' => $entry->getKey()]) }}">
                                        @csrf
                                        <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--danger" type="submit" title="Remove"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Pick a map</h2>
            <form method="POST" action="{{ route('panel.admin.daily-challenge.store') }}" class="tomyum-panel">
                @csrf
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Beatmap link or id (a difficulty, with a leaderboard)</span>
                        <input class="input-text" type="text" name="beatmap" required placeholder="https://osu.ppy.sh/beatmaps/129891">
                    </label>
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 160px">
                        <span class="input-container__label">Ruleset</span>
                        <select class="input-text" name="ruleset">
                            <option value="">the map's own</option>
                            @foreach ($rulesets as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 160px">
                        <span class="input-container__label">Goes</span>
                        <select class="input-text" name="position">
                            <option value="next">next (tomorrow)</option>
                            <option value="last">last</option>
                        </select>
                    </label>
                </div>
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Required mods (e.g. HD, or DT for a speed day)</span>
                        <input class="input-text" type="text" name="required_mods" placeholder="none">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Allowed mods (left empty: {{ $defaultAllowed[0] }}; mania {{ $defaultAllowed[3] }})</span>
                        <input class="input-text" type="text" name="allowed_mods" placeholder="the usual, no rate changes">
                    </label>
                </div>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Description (optional, shown in lazer)</span>
                    <input class="input-text" type="text" name="description" maxlength="255">
                </label>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Queue it</button></div>
            </form>
        </div>
    </div>
@endsection
