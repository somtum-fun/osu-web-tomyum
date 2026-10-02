{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: Project Loved's overview (LovedController@index): what it is, the latest round in short,
    the most wanted requests, past rounds and the team (bem/tomyum-loved.less).
--}}
@php
    use App\Libraries\TomYumLoved;
    use App\Models\Beatmap;

    $latestCounts = $latestNominations->countBy('ruleset_id');
@endphp
@extends('master')

@section('content')
    @include('loved._header', ['current' => 'overview'])

    <div class="osu-page osu-page--generic-compact tomyum-loved">
        <div class="page-extra">
            <h2 class="title title--page-extra">About Project Loved</h2>
            <div class="tomyum-loved__about">
                <div>
                    <p>
                        Some maps will never be ranked: they're too long, too hard, too strange, or their mapper moved on.
                        Project Loved gives the ones players love a leaderboard anyway. Loved maps keep scores and
                        leaderboards like ranked ones, but give no pp.
                    </p>
                    <p>
                        Got a map in mind? <a href="{{ route('loved.requests') }}">Request it</a>, or support
                        what others asked for: the team picks each round's maps from the requests.
                        Every map loved so far is in the <a href="{{ route('beatmapsets.index', ['s' => 'loved']) }}">beatmap listing</a>.
                    </p>
                </div>
                <ol class="tomyum-loved__steps">
                    <li><strong>Picking</strong> The Loved team chooses maps for each mode and writes why they deserve it.</li>
                    <li><strong>Voting</strong> Everyone votes yes or no on each map, here or in its forum topic.</li>
                    <li><strong>Results</strong> Maps with enough yes votes are Loved when the round closes.</li>
                </ol>
            </div>
        </div>

        <div class="page-extra">
            <h2 class="title title--page-extra">Questions</h2>
            @include('loved._faq')
        </div>

        <div class="page-extra">
            @if ($latest === null)
                <h2 class="title title--page-extra">Latest round</h2>
                <p class="tomyum-loved__muted">No round has started yet. The next one will show up here.</p>
            @else
                <div class="tomyum-loved__section-head">
                    <h2 class="title title--page-extra">{{ $latest->name }}</h2>
                    <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('loved.rounds.show', ['round' => $latest->getKey()]) }}">
                        @if ($latest->isVoting())
                            <i class="fas fa-vote-yea"></i> Vote now
                        @else
                            <i class="fas fa-list"></i> See the results
                        @endif
                    </a>
                </div>
                <div class="tomyum-loved__status">
                    @if ($latest->isVoting())
                        <span class="tomyum-loved__status-item tomyum-loved__status-item--live">
                            <i class="fas fa-circle"></i> Voting ends {!! timeago($latest->voting_ends_at) !!}
                        </span>
                    @else
                        <span class="tomyum-loved__status-item">Closed {!! timeago($latest->closed_at) !!}</span>
                        <span class="tomyum-loved__status-item tomyum-loved__status-item--loved">
                            <i class="fas fa-heart"></i> {{ $latest->loved_count }} of {{ $latest->nominations_count }} maps Loved
                        </span>
                    @endif
                    <span class="tomyum-loved__status-item">
                        @foreach (Beatmap::MODES as $mode => $modeId)
                            <a href="{{ route('loved.rounds.show', ['round' => $latest->getKey(), 'mode' => $mode]) }}"
                                class="tomyum-loved__mode-count" title="{{ TomYumLoved::MODE_NAMES[$modeId] }}">
                                <span class="fal fa-extra-mode-{{ $mode }}"></span> {{ $latestCounts[$modeId] ?? 0 }}
                            </a>
                        @endforeach
                    </span>
                </div>

                <div class="tomyum-loved__tiles">
                    @foreach ($latestNominations as $nomination)
                        @php
                            $set = $nomination->beatmapset;
                            $passed = $latest->isClosed() ? $nomination->passed : null;
                        @endphp
                        <a
                            class="{{ class_with_modifiers('tomyum-loved-tile', ['loved' => $passed === true, 'failed' => $passed === false]) }}"
                            href="{{ route('loved.rounds.show', ['round' => $latest->getKey(), 'mode' => Beatmap::modeStr($nomination->ruleset_id)]) }}#nomination-{{ $nomination->getKey() }}"
                            style="background-image: url('{{ $set->coverURL('card') }}')"
                        >
                            <span class="tomyum-loved-tile__mode fal fa-extra-mode-{{ Beatmap::modeStr($nomination->ruleset_id) }}"
                                title="{{ TomYumLoved::MODE_NAMES[$nomination->ruleset_id] }}"></span>
                            @if ($passed === true)
                                <span class="tomyum-loved-tile__badge"><i class="fas fa-heart"></i> Loved</span>
                            @endif
                            <span class="tomyum-loved-tile__text">
                                <span class="tomyum-loved-tile__title u-ellipsis-overflow">{{ $set->title }}</span>
                                <span class="tomyum-loved-tile__artist u-ellipsis-overflow">{{ $set->artist }} · {{ $set->creator }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="tomyum-loved__columns">
            <div class="page-extra">
                <div class="tomyum-loved__section-head">
                    <h2 class="title title--page-extra">Most wanted</h2>
                    <a href="{{ route('loved.requests') }}">All requests <i class="fas fa-angle-right"></i></a>
                </div>
                @if ($topRequests->isEmpty())
                    <p class="tomyum-loved__muted">No requests yet. <a href="{{ route('loved.requests') }}">Ask for a map</a>!</p>
                @else
                    <ul class="tomyum-loved-list">
                        @foreach ($topRequests as $request)
                            @php
                                $set = $request->beatmapset;
                                $mode = Beatmap::modeStr($request->ruleset_id);
                            @endphp
                            <li>
                                <a class="tomyum-loved-list__item" href="{{ route('loved.requests', ['mode' => $mode]) }}#request-{{ $request->getKey() }}">
                                    <span class="tomyum-loved-list__cover" style="background-image: url('{{ $set->coverURL('list') }}')"></span>
                                    <span class="tomyum-loved-list__text">
                                        <span class="tomyum-loved-list__title u-ellipsis-overflow">{{ $set->title }}</span>
                                        <span class="tomyum-loved-list__meta u-ellipsis-overflow">
                                            <span class="fal fa-extra-mode-{{ $mode }}"></span> {{ $set->artist }} · {{ $set->creator }}
                                        </span>
                                    </span>
                                    <span class="tomyum-loved-list__value" title="Supporters"><i class="fas fa-heart"></i> {{ i18n_number_format($request->supporters_count) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="page-extra">
                <h2 class="title title--page-extra">Rounds</h2>
                @if ($rounds->isEmpty())
                    <p class="tomyum-loved__muted">None yet.</p>
                @else
                    <ul class="tomyum-loved-list">
                        @foreach ($rounds as $round)
                            <li>
                                <a class="tomyum-loved-list__item" href="{{ route('loved.rounds.show', ['round' => $round->getKey()]) }}">
                                    <span class="tomyum-loved-list__text">
                                        <span class="tomyum-loved-list__title">{{ $round->name }}</span>
                                        <span class="tomyum-loved-list__meta">
                                            @if ($round->isVoting())
                                                <span class="tomyum-loved__live">Voting now</span>, ends {!! timeago($round->voting_ends_at) !!}
                                            @else
                                                Closed {!! timeago($round->closed_at) !!}
                                            @endif
                                            · {{ $round->nominations_count }} {{ $round->nominations_count === 1 ? 'map' : 'maps' }}
                                        </span>
                                    </span>
                                    @if ($round->isClosed())
                                        <span class="tomyum-loved-list__value tomyum-loved-list__value--loved" title="Loved">
                                            <i class="fas fa-heart"></i> {{ $round->loved_count }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        @if (count($team) > 0)
            <div class="page-extra">
                <h2 class="title title--page-extra">The Loved team</h2>
                <p class="tomyum-loved__muted">They pick the maps for each round; their badge shows the modes they pick for.</p>
                <div class="tomyum-loved__team">
                    @foreach ($team as $memberJson)
                        <div class="js-react u-contents" data-react="user-card" data-user="{{ json_encode($memberJson) }}"></div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
