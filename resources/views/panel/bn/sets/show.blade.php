{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    use App\Http\Controllers\Panel\BnSetsController;

    $modes = ['osu!', 'osu!taiko', 'osu!catch', 'osu!mania'];
    $title = $beatmapset !== null ? "{$beatmapset->artist} - {$beatmapset->title}" : "Beatmap set #{$setId}";
@endphp
@extends('master', ['titlePrepend' => $title])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.bn.index'), 'title' => 'BN panel'],
        ['url' => route('panel.bn.sets.index'), 'title' => 'Manage sets'],
        ['title' => $title],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        @php
            $statusValue = ['graveyard' => -2, 'wip' => -1, 'pending' => 0, 'ranked' => 1, 'approved' => 2, 'qualified' => 3, 'loved' => 4];
            $starColour = [App\Http\Controllers\Panel\DailyChallengeController::class, 'starColour'];
        @endphp
        <div class="tomyum-panel-cover" style="--cover: url('{{ $beatmapset?->coverURL('cover') }}')">
            <div class="tomyum-panel-cover__text">
                <h1 class="tomyum-panel-cover__title">{{ $title }}</h1>
                @if ($beatmapset !== null)
                    <div class="tomyum-panel-cover__meta">
                        @include('panel._status', ['approved' => $beatmapset->approved])
                        <span>mapped by <strong>{{ $beatmapset->creator }}</strong></span>
                        <span>#{{ $setId }}</span>
                    </div>
                @endif
            </div>
            <div class="tomyum-panel-cover__buttons">
                <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ route('beatmapsets.show', ['beatmapset' => $setId]) }}"><i class="fas fa-external-link-alt"></i>On the site</a>
                <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="https://osu.ppy.sh/beatmapsets/{{ $setId }}" rel="noopener" target="_blank"><i class="fas fa-external-link-alt"></i>On osu!</a>
            </div>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-star"></i>Ranked status</h2>
            <p class="tomyum-panel__hint">
                Tick difficulties to change only those, or none for the whole set. A status set here is kept when the set
                is refreshed from bancho, and takes effect in-game immediately. Existing scores are recalculated (pp, bests,
                players' stats and rankings). "Back to bancho" undoes staff changes: the difficulties follow osu!'s status again.
            </p>
            @if ($serverError !== null)
                <p>{{ $serverError }}</p>
            @endif
            <form method="POST" action="{{ route('panel.bn.sets.status', ['set' => $setId]) }}">
                @csrf
                <table class="tomyum-panel__table">
                    <tr><th></th><th>Difficulty</th><th>Mode</th><th>Stars</th><th>Status</th></tr>
                    @forelse ($difficulties as $difficulty)
                        <tr>
                            <td style="width: 30px"><input type="checkbox" name="beatmaps[]" value="{{ $difficulty['id'] }}"></td>
                            <td>{{ $difficulty['version'] }}</td>
                            <td>{{ $modes[$difficulty['mode']] ?? $difficulty['mode'] }}</td>
                            <td>
                                <span class="tomyum-stars" style="--colour: {{ $starColour($difficulty['starRating']) }}; --text: {{ $difficulty['starRating'] >= 6.5 ? '#fff' : 'hsl(var(--hsl-b6))' }}">
                                    <i class="fas fa-star"></i>{{ number_format($difficulty['starRating'], 2) }}
                                </span>
                            </td>
                            <td>
                                @if (isset($statusValue[$difficulty['status']]))
                                    @include('panel._status', ['approved' => $statusValue[$difficulty['status']]])
                                @else
                                    {{ ucfirst($difficulty['status']) }}
                                @endif
                                @if ($difficulty['staffSet'])
                                    <span class="tomyum-panel__badge tomyum-panel__badge--success">set by staff</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="tomyum-panel__hint">
                                The game server doesn't have this set's difficulties yet; changing the status imports it first.
                            </td>
                        </tr>
                    @endforelse
                </table>
                <div class="tomyum-panel__row" style="margin-top: 12px">
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 220px">
                        <span class="input-container__label">New status</span>
                        <select class="input-text" name="status" required>
                            @foreach (BnSetsController::SETTABLE_STATUSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Change status</button>
                </div>
            </form>
        </div>

        {{-- TomYum: who nominated it; admins can set them by hand (BnSetsController@nominators) --}}
        @if ($beatmapset !== null)
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-thumbs-up"></i>Nominators</h2>
                <p>
                    @if ($nominators->isEmpty())
                        <span class="tomyum-panel__muted">Nobody has nominated it here.</span>
                    @else
                        Nominated by
                        @foreach ($nominators as $nominator)
                            <a href="{{ route('users.show', ['user' => $nominator->getKey()]) }}">{{ $nominator->username }}</a>@if (!$loop->last), @endif
                        @endforeach
                    @endif
                </p>
                @if ($canSetNominators)
                    <p class="tomyum-panel__hint">
                        For a set ranked here by hand, or a bancho map that wasn't ranked there: the beatmap page shows these players
                        under "Nominated by", and the set's history records their nominations. On a pending set, enough of them
                        ({{ $GLOBALS['cfg']['osu']['beatmapset']['required_nominations'] }}) qualify it, as BNs nominating on the
                        discussion page do. Leave it empty to clear them.
                    </p>
                    <form method="POST" action="{{ route('panel.bn.sets.nominators', ['set' => $setId]) }}" class="tomyum-panel__row">
                        @csrf
                        <label class="input-container tomyum-panel__field">
                            <span class="input-container__label">Players, by name or id (comma-separated)</span>
                            <input class="input-text" type="text" name="nominators" value="{{ $nominators->pluck('username')->implode(', ') }}">
                        </label>
                        <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button></div>
                    </form>
                @endif
            </div>
        @endif

        @if ($isCustom && $beatmapset !== null)
            {{-- TomYum: an uploaded set's featured artist track. --}}
            <div class="tomyum-panel__card" id="track">
                <h2><i class="fas fa-music"></i>Featured artist track</h2>
                <p class="tomyum-panel__hint">
                    The song's track, when it's a featured artist's: the set gets the featured artist badge, and shows
                    under the artist's Beatmaps button.
                </p>
                @if ($track !== null)
                    <div class="panel-track">
                        <span class="panel-track__cover" style="background-image: url('{{ $track->cover_url }}')"></span>
                        <span class="panel-track__text">
                            <strong>{{ $track->title }}</strong>
                            <span class="tomyum-panel__muted">by {{ $track->artist?->name }} · {{ $track->genre }} · {{ round($track->bpm) }} BPM</span>
                        </span>
                        <form method="POST" action="{{ route('panel.bn.sets.track', ['set' => $setId]) }}">
                            @csrf
                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit">Take it off</button>
                        </form>
                    </div>
                @else
                    <p class="tomyum-panel__muted">None: it isn't a featured artist set.</p>
                @endif

                <form method="GET" action="{{ route('panel.bn.sets.show', ['set' => $setId]) }}#track" class="panel-track__search">
                    <input class="input-text" type="text" name="track_q" value="{{ $trackQuery }}" placeholder="A track's title or its artist" aria-label="Find a track">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-search"></i>Find</button>
                </form>
                @if ($trackQuery !== '')
                    @if (count($trackResults) === 0)
                        <p class="tomyum-panel__empty">No track matches "{{ $trackQuery }}".</p>
                    @else
                        <div class="panel-track__results">
                            @foreach ($trackResults as $result)
                                <div class="panel-track">
                                    <span class="panel-track__cover" style="background-image: url('{{ $result->cover_url }}')"></span>
                                    <span class="panel-track__text">
                                        <strong>{{ $result->title }}</strong>
                                        <span class="tomyum-panel__muted">by {{ $result->artist?->name }} · {{ $result->genre }} · {{ round($result->bpm) }} BPM · {{ sprintf('%d:%02d', intdiv($result->length, 60), $result->length % 60) }}</span>
                                    </span>
                                    @if ($track?->getKey() === $result->getKey())
                                        <span class="tomyum-panel__pill tomyum-panel__pill--good">This one</span>
                                    @else
                                        <form method="POST" action="{{ route('panel.bn.sets.track', ['set' => $setId]) }}">
                                            @csrf
                                            <input type="hidden" name="track_id" value="{{ $result->getKey() }}">
                                            <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Use this</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @endif

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-sync-alt"></i>Refresh from bancho</h2>
            <p class="tomyum-panel__hint">
                Re-fetches the title, difficulties and stats from osu!. Statuses set by staff are kept.
            </p>
            <form method="POST" action="{{ route('panel.bn.sets.refresh', ['set' => $setId]) }}">
                @csrf
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Refresh</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-history"></i>History</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">No staff actions on this set yet.</p>
            @else
                @include('panel.bn._log_table', ['showJob' => false])
            @endif
        </div>
    </div>
@endsection
