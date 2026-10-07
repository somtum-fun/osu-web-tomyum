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
        @php
            $rulesets = [['osu', 'osu!'], ['taiko', 'osu!taiko'], ['fruits', 'osu!catch'], ['mania', 'osu!mania']];
            // The set's numbers, under its title.
            $length = $beatmapset?->beatmaps->max('total_length');
            $facts = $beatmapset === null ? [] : array_filter([
                ['fa-layer-group', count($difficulties) ?: $beatmapset->beatmaps->count(), 'difficulties'],
                ['fa-drum', $beatmapset->bpm > 0 ? round((float) $beatmapset->bpm) : null, 'BPM'],
                ['fa-clock', $length > 0 ? sprintf('%d:%02d', intdiv((int) $length, 60), $length % 60) : null, 'long'],
                ['fa-play-circle', number_format((int) $beatmapset->play_count), 'plays'],
                ['fa-heart', number_format((int) $beatmapset->favourite_count), 'favourites'],
            ], fn ($fact) => $fact[1] !== null);
        @endphp
        <div class="tomyum-panel-cover" style="--cover: url('{{ $beatmapset?->coverURL('cover') }}')">
            <div class="tomyum-panel-cover__text">
                <h1 class="tomyum-panel-cover__title">{{ $title }}</h1>
                @if ($beatmapset !== null)
                    <div class="tomyum-panel-cover__meta">
                        @include('panel._status', ['approved' => $beatmapset->approved])
                        <span>mapped by <strong>{{ $beatmapset->creator }}</strong></span>
                        <span class="tomyum-panel__muted">#{{ $setId }}</span>
                    </div>
                    <div class="tomyum-panel-cover__facts">
                        @foreach ($facts as [$icon, $value, $label])
                            <span><i class="fas {{ $icon }}"></i><strong>{{ $value }}</strong> {{ $label }}</span>
                        @endforeach
                        @if ($beatmapset->last_update)
                            <span><i class="fas fa-pen"></i>updated {!! timeago($beatmapset->last_update) !!}</span>
                        @endif
                    </div>
                @endif
            </div>
            <div class="tomyum-panel-cover__buttons">
                <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('beatmapsets.show', ['beatmapset' => $setId]) }}"><i class="fas fa-external-link-alt"></i>On the site</a>
                <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('beatmapsets.discussion', ['beatmapset' => $setId]) }}"><i class="fas fa-comments"></i>Discussion</a>
                @unless ($isCustom)
                    <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="https://osu.ppy.sh/beatmapsets/{{ $setId }}" rel="noopener" target="_blank"><i class="fas fa-external-link-alt"></i>On osu!</a>
                @endunless
            </div>
        </div>

        {{-- The set's status and nominators on the left; the rarer tools beside them. --}}
        <div class="tomyum-panel__layout">
            <div class="tomyum-panel__layout-main">
                <div class="tomyum-panel__card">
                    <h2><i class="fas fa-star"></i>Ranked status</h2>
                    @if ($serverError !== null)
                        <p class="tomyum-panel__hint">{{ $serverError }}</p>
                    @endif
                    <form method="POST" action="{{ route('panel.bn.sets.status', ['set' => $setId]) }}" class="js-set-status">
                        @csrf
                        @if (count($settableStatuses) > 0)
                            {{-- What changes, to what: above the list it applies to. --}}
                            <div class="tomyum-panel__toolbar">
                                <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 220px">
                                    <span class="input-container__label">New status</span>
                                    <select class="input-text" name="status" required>
                                        @foreach ($settableStatuses as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-check"></i>Change status</button>
                                <span class="tomyum-panel__hint js-set-status-scope">Applies to the whole set</span>
                            </div>
                        @endif
                        <table class="tomyum-panel__table">
                            <tr>
                                <th style="width: 30px">
                                    @if (count($difficulties) > 1 && count($settableStatuses) > 0)
                                        <input type="checkbox" class="js-set-status-all" title="Select every difficulty" aria-label="Select every difficulty">
                                    @endif
                                </th>
                                <th>Difficulty</th><th>Mode</th><th>Stars</th><th>Status</th>
                            </tr>
                            @forelse ($difficulties as $difficulty)
                                @php([$rulesetKey, $rulesetName] = $rulesets[$difficulty['mode']] ?? ['osu', (string) $difficulty['mode']])
                                <tr>
                                    <td>
                                        @if (count($settableStatuses) > 0)
                                            <input type="checkbox" name="beatmaps[]" value="{{ $difficulty['id'] }}" class="js-set-status-one" aria-label="{{ $difficulty['version'] }}">
                                        @endif
                                    </td>
                                    <td><strong>{{ $difficulty['version'] }}</strong></td>
                                    <td class="tomyum-panel__nowrap"><i class="fal fa-extra-mode-{{ $rulesetKey }}" title="{{ $rulesetName }}"></i> {{ $rulesetName }}</td>
                                    <td>
                                        <span class="tomyum-stars" style="--colour: {{ $starColour($difficulty['starRating']) }}; --text: {{ $difficulty['starRating'] >= 6.5 ? '#fff' : 'hsl(var(--hsl-b6))' }}">
                                            <i class="fas fa-star"></i>{{ number_format($difficulty['starRating'], 2) }}
                                        </span>
                                    </td>
                                    <td class="tomyum-panel__nowrap">
                                        @if (isset($statusValue[$difficulty['status']]))
                                            @include('panel._status', ['approved' => $statusValue[$difficulty['status']]])
                                        @else
                                            {{ ucfirst($difficulty['status']) }}
                                        @endif
                                        @if ($difficulty['staffSet'])
                                            <span class="tomyum-panel__badge tomyum-panel__badge--success" title="Kept when the set is refreshed from bancho">by staff</span>
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
                        @if (count($settableStatuses) === 0)
                            <p class="tomyum-panel__hint" style="margin-top: 10px">BNs on probation nominate on the set's discussion page.</p>
                        @endif
                    </form>
                    <details class="tomyum-panel__more">
                        <summary>How status changes work</summary>
                        <p class="tomyum-panel__hint">
                            Tick difficulties to change only those, or none for the whole set. A status set here is kept when
                            the set is refreshed from bancho, and takes effect in-game immediately; existing scores are
                            recalculated (pp, bests, players' stats and rankings). "Back to bancho" undoes staff changes: the
                            difficulties follow osu!'s status again.
                        </p>
                        <p class="tomyum-panel__hint">
                            Ranking or approving here skips the nominations and the qualified period, so it's for the NAT and
                            admins. A qualified set is ranked by itself after the qualified period. Whether a newly ranked
                            difficulty keeps its scores (they get pp) or starts with an empty leaderboard is the game server's
                            ranking setting.
                        </p>
                    </details>
                </div>

                {{-- TomYum: who nominated it; admins can set them by hand (BnSetsController@nominators) --}}
                @if ($beatmapset !== null)
                    <div class="tomyum-panel__card">
                        <h2><i class="fas fa-thumbs-up"></i>Nominators</h2>
                        @if ($nominators->isEmpty())
                            <p class="tomyum-panel__hint">Nobody has nominated it here yet.</p>
                        @else
                            <div class="tomyum-panel__chips">
                                @foreach ($nominators as $nominator)
                                    <a class="tomyum-panel__chip" href="{{ route('users.show', ['user' => $nominator->getKey()]) }}">{{ $nominator->username }}</a>
                                @endforeach
                            </div>
                        @endif
                        @if ($canSetNominators)
                            <form method="POST" action="{{ route('panel.bn.sets.nominators', ['set' => $setId]) }}" class="tomyum-panel__row" style="margin-top: 12px">
                                @csrf
                                <label class="input-container tomyum-panel__field">
                                    <span class="input-container__label">Set by hand: players, by name or id (comma-separated; empty clears them)</span>
                                    <input class="input-text" type="text" name="nominators" value="{{ $nominators->pluck('username')->implode(', ') }}">
                                </label>
                                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button></div>
                            </form>
                            <details class="tomyum-panel__more">
                                <summary>When to set them by hand</summary>
                                <p class="tomyum-panel__hint">
                                    For a set ranked here by hand, or a bancho map that wasn't ranked there: the beatmap page shows
                                    these players under "Nominated by", and the set's history records their nominations. On a pending
                                    set, enough of them ({{ $GLOBALS['cfg']['osu']['beatmapset']['required_nominations'] }}) qualify
                                    it, as BNs nominating on the discussion page do.
                                </p>
                            </details>
                        @endif
                    </div>
                @endif
            </div>

            <div class="tomyum-panel__layout-side">
                {{-- TomYum: osu!'s graveyard and WIP sets enter the nomination flow from here (BnSetsController@open) --}}
                @if ($canOpen)
                    <div class="tomyum-panel__card">
                        <h2><i class="fas fa-door-open"></i>Open for nominations</h2>
                        <p class="tomyum-panel__hint">
                            osu! has it as {{ $beatmapset->isGraveyard() ? 'graveyard' : 'WIP' }}. Opening it makes it pending
                            here and in-game, so BNs can nominate it.
                        </p>
                        <form method="POST" action="{{ route('panel.bn.sets.open', ['set' => $setId]) }}">
                            @csrf
                            <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-door-open"></i>Open it</button>
                        </form>
                    </div>
                @endif

                @if ($isCustom && $beatmapset !== null)
                    {{-- TomYum: an uploaded set's featured artist track. --}}
                    <div class="tomyum-panel__card" id="track">
                        <h2><i class="fas fa-music"></i>Featured artist track</h2>
                        @if ($track !== null)
                            <div class="panel-track">
                                <span class="panel-track__cover" style="background-image: url('{{ $track->cover_url }}')"></span>
                                <span class="panel-track__text">
                                    <strong>{{ $track->title }}</strong>
                                    <span class="tomyum-panel__muted">by {{ $track->artist?->name }} · {{ round($track->bpm) }} BPM</span>
                                </span>
                                <form method="POST" action="{{ route('panel.bn.sets.track', ['set' => $setId]) }}">
                                    @csrf
                                    <button class="tomyum-panel__icon-button" type="submit" title="Take it off"><i class="fas fa-times"></i></button>
                                </form>
                            </div>
                        @else
                            <p class="tomyum-panel__hint">None. A featured artist's song gets the badge and shows under the artist.</p>
                        @endif

                        <form method="GET" action="{{ route('panel.bn.sets.show', ['set' => $setId]) }}#track" class="panel-track__search">
                            <input class="input-text" type="text" name="track_q" value="{{ $trackQuery }}" placeholder="Title or artist" aria-label="Find a track">
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
                                                <span class="tomyum-panel__muted">by {{ $result->artist?->name }} · {{ sprintf('%d:%02d', intdiv($result->length, 60), $result->length % 60) }}</span>
                                            </span>
                                            @if ($track?->getKey() === $result->getKey())
                                                <span class="tomyum-panel__pill tomyum-panel__pill--good">This one</span>
                                            @else
                                                <form method="POST" action="{{ route('panel.bn.sets.track', ['set' => $setId]) }}">
                                                    @csrf
                                                    <input type="hidden" name="track_id" value="{{ $result->getKey() }}">
                                                    <button class="btn-osu-big btn-osu-big--rounded-small" type="submit">Use</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                @unless ($isCustom)
                    <div class="tomyum-panel__card">
                        <h2><i class="fas fa-sync-alt"></i>Refresh from bancho</h2>
                        <p class="tomyum-panel__hint">Title, difficulties and stats again from osu!; statuses set by staff are kept.</p>
                        <form method="POST" action="{{ route('panel.bn.sets.refresh', ['set' => $setId]) }}">
                            @csrf
                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit"><i class="fas fa-sync-alt"></i>Refresh</button>
                        </form>
                    </div>
                @endunless

                <div class="tomyum-panel__card">
                    <h2><i class="fas fa-history"></i>History</h2>
                    @if (count($entries) === 0)
                        <p class="tomyum-panel__hint">No staff actions on this set yet.</p>
                    @else
                        <ol class="tomyum-panel__timeline">
                            @foreach ($entries as $entry)
                                <li>
                                    <span class="tomyum-panel__muted">{!! timeago(Carbon\Carbon::parse($entry['createdAt'], 'UTC')) !!}</span>
                                    <span>@include('panel._who') · {{ preg_replace('/(?<![0-9])1 difficulties/', '1 difficulty', $entry['summary']) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </div>

        <script>
            // Ranked status: "select all", and what the change applies to.
            (function () {
                var form = document.querySelector('.js-set-status');
                if (form === null) return;
                var all = form.querySelector('.js-set-status-all');
                var ones = Array.prototype.slice.call(form.querySelectorAll('.js-set-status-one'));
                var scope = form.querySelector('.js-set-status-scope');
                var update = function () {
                    var ticked = ones.filter(function (box) { return box.checked; }).length;
                    if (scope !== null) {
                        scope.textContent = ticked === 0 || ticked === ones.length
                            ? 'Applies to the whole set'
                            : 'Applies to ' + ticked + ' ' + (ticked === 1 ? 'difficulty' : 'difficulties');
                    }
                    if (all !== null) {
                        all.checked = ticked === ones.length && ticked > 0;
                        all.indeterminate = ticked > 0 && ticked < ones.length;
                    }
                };
                if (all !== null) {
                    all.addEventListener('change', function () {
                        ones.forEach(function (box) { box.checked = all.checked; });
                        update();
                    });
                }
                ones.forEach(function (box) { box.addEventListener('change', update); });
            })();
        </script>
    </div>
@endsection
