{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: a Project Loved round (LovedController@round): its maps in one mode, each osu-web's beatmap
    card with the captain's write-up and the poll under it (bem/tomyum-loved.less).
--}}
@php
    use App\Libraries\TomYumLoved;
    use App\Models\Beatmap;

    $rulesetId = Beatmap::MODES[$ruleset];
@endphp
@extends('master', ['titlePrepend' => $round->name])

@section('content')
    @include('loved._header', [
        'current' => $round,
        'urlFn' => fn ($mode) => route('loved.rounds.show', ['round' => $round->getKey(), 'mode' => $mode]),
    ])

    <div class="osu-page osu-page--generic-compact tomyum-loved">
        <div class="page-extra">
            <h2 class="title title--page-extra">
                {{ $round->name }} · {{ TomYumLoved::MODE_NAMES[$rulesetId] }}
            </h2>
            <div class="tomyum-loved__status">
                @if ($round->isVoting())
                    <span class="tomyum-loved__status-item tomyum-loved__status-item--live">
                        <i class="fas fa-circle"></i> Voting ends {!! timeago($round->voting_ends_at) !!}
                    </span>
                @else
                    <span class="tomyum-loved__status-item">Closed {!! timeago($round->closed_at) !!}</span>
                    <span class="tomyum-loved__status-item tomyum-loved__status-item--loved">
                        <i class="fas fa-heart"></i> {{ $lovedCount }} of {{ $counts->sum() }} maps Loved
                    </span>
                @endif
                <span class="tomyum-loved__status-item">
                    Needs {{ $round->threshold }}% yes from at least {{ i18n_number_format($round->min_votes) }} {{ $round->min_votes === 1 ? 'vote' : 'votes' }}
                </span>
                <span class="tomyum-loved__status-item">
                    @foreach (Beatmap::MODES as $mode => $modeId)
                        <a href="{{ route('loved.rounds.show', ['round' => $round->getKey(), 'mode' => $mode]) }}"
                            class="{{ class_with_modifiers('tomyum-loved__mode-count', ['active' => $modeId === $rulesetId]) }}"
                            title="{{ TomYumLoved::MODE_NAMES[$modeId] }}">
                            <span class="fal fa-extra-mode-{{ $mode }}"></span> {{ $counts[$modeId] ?? 0 }}
                        </a>
                    @endforeach
                </span>
            </div>

            @if ($nominations->isEmpty())
                <p class="tomyum-loved__muted">No {{ TomYumLoved::MODE_NAMES[$rulesetId] }} maps in this round.</p>
            @else
                <div class="tomyum-loved__entries">
                    @foreach ($nominations as $item)
                        @php
                            $nomination = $item['nomination'];
                            $total = $item['yes'] + $item['no'];
                            $percent = $total === 0 ? 0 : $item['yes'] * 100 / $total;
                            // The result shows once the round is closed (closing counts first, then loves).
                            $passed = $round->isClosed() ? $nomination->passed : null;
                            $pollOpen = $nomination->topic?->poll()->isOpen() ?? false;
                        @endphp
                        @continue($item['beatmapsetJson'] === null)
                        <div
                            class="{{ class_with_modifiers('tomyum-loved-entry', ['loved' => $passed === true, 'failed' => $passed === false]) }}"
                            id="nomination-{{ $nomination->getKey() }}"
                        >
                            <div
                                class="js-react u-contents"
                                data-beatmapset-panel="{{ json_encode(['beatmapset' => $item['beatmapsetJson']]) }}"
                                data-react="beatmapset-panel"
                            >
                                <div class="beatmapset-panel beatmapset-panel--size-normal"></div>
                            </div>

                            <div class="tomyum-loved-entry__body">
                                @if ($nomination->captain !== null || $nomination->descriptionHTML() !== null)
                                    <div class="tomyum-loved-entry__writeup">
                                        @if ($nomination->captain !== null)
                                            <div class="tomyum-loved-entry__captain">
                                                Picked by
                                                <a class="js-usercard" data-user-id="{{ $nomination->captain_id }}"
                                                    href="{{ route('users.show', ['user' => $nomination->captain_id]) }}">{{ $nomination->captain->username }}</a>
                                            </div>
                                        @endif
                                        @if ($nomination->descriptionHTML() !== null)
                                            <div class="tomyum-loved-entry__description">{!! $nomination->descriptionHTML() !!}</div>
                                        @endif
                                    </div>
                                @endif

                                <div class="tomyum-loved-entry__poll">
                                    <div class="tomyum-loved-entry__numbers">
                                        @if ($passed === true)
                                            <strong class="tomyum-loved-entry__result tomyum-loved-entry__result--loved"><i class="fas fa-heart"></i> Loved</strong>
                                        @elseif ($passed === false)
                                            <strong class="tomyum-loved-entry__result">Didn't pass</strong>
                                        @endif
                                        @if ($total === 0)
                                            <span>No votes yet</span>
                                        @else
                                            <span><strong>{{ round($percent, 1) }}%</strong> yes · {{ i18n_number_format($total) }} {{ $total === 1 ? 'vote' : 'votes' }}</span>
                                        @endif
                                        @if ($nomination->topic_id !== null)
                                            <a href="{{ route('forum.topics.show', ['topic' => $nomination->topic_id]) }}">
                                                <i class="fas fa-comments"></i> Discuss
                                            </a>
                                        @endif
                                    </div>
                                    <div
                                        class="{{ class_with_modifiers('tomyum-loved-entry__bar', ['empty' => $total === 0]) }}"
                                        style="--yes: {{ round($percent, 2) }}%; --needed: {{ $round->threshold }}%"
                                        title="{{ $round->threshold }}% needed"
                                    ></div>

                                    @if ($round->isVoting() && !$pollOpen)
                                        <div class="tomyum-loved-entry__note">Voting has ended; the results come when the round closes.</div>
                                    @elseif ($round->isVoting() && $item['cannotVote'] !== null)
                                        <div class="tomyum-loved-entry__note">{{ $item['cannotVote'] }}</div>
                                    @elseif ($round->isVoting())
                                        <form method="POST" action="{{ route('loved.vote', ['nomination' => $nomination->getKey()]) }}" class="tomyum-loved-entry__buttons">
                                            @csrf
                                            <button type="submit" name="vote" value="yes"
                                                class="{{ class_with_modifiers('tomyum-loved-entry__vote', ['active' => $item['votedFor'] === TomYumLoved::OPTION_YES, 'yes' => true]) }}">
                                                <i class="fas fa-check"></i> Yes
                                            </button>
                                            <button type="submit" name="vote" value="no"
                                                class="{{ class_with_modifiers('tomyum-loved-entry__vote', ['active' => $item['votedFor'] === TomYumLoved::OPTION_NO, 'no' => true]) }}">
                                                <i class="fas fa-times"></i> No
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
