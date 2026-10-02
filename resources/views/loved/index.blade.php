{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: Project Loved (LovedController@index): ask for a map, and the requests in one status, open
    ones by their reviews' score (bem/tomyum-loved.less).
--}}
@php
    use App\Libraries\TomYumLoved;
    use App\Models\Beatmap;

    $statusTitles = [
        'open' => 'Requests',
        'loved' => 'Loved by the team',
        'rejected' => 'Rejected',
        'removed' => 'Removed',
    ];
    $loveStatus = session('loved_status');
    $user = Auth::user();
    $modeName = $ruleset === null ? null : TomYumLoved::MODE_NAMES[Beatmap::MODES[$ruleset]];
@endphp
@extends('master', ['titlePrepend' => $status === 'open' ? null : $statusTitles[$status]])

@section('content')
    @include('loved._header', [
        'current' => $status,
        'urlFn' => fn ($mode) => route('loved.index', array_filter([
            'status' => $status === 'open' ? null : $status,
            'mode' => $mode === $ruleset ? null : $mode,
        ])),
    ])

    <div class="osu-page osu-page--generic-compact tomyum-loved">
        @if ($status === 'open')
            <div class="page-extra" id="ask">
                <h2 class="title title--page-extra">Project Loved</h2>
                <div class="tomyum-loved__about">
                    <div>
                        <p>
                            Some maps will never be ranked: they're too long, too hard, too strange, or their mapper moved on.
                            Loved gives the ones players love a leaderboard anyway (scores, but no pp).
                        </p>
                        <ol class="tomyum-loved__steps">
                            <li><strong>Ask</strong> Paste a map's link, pick the mode and say why it deserves Loved.</li>
                            <li><strong>Review</strong> Others say if they're for or against it, and why. The best-liked requests rise to the top.</li>
                            <li><strong>Decide</strong> The Loved team loves the map, or rejects it with a reason.</li>
                        </ol>
                    </div>
                    <div>
                        @if ($loveStatus !== null)
                            <div class="{{ class_with_modifiers('tomyum-loved__alert', [$loveStatus['type']]) }}">{{ $loveStatus['message'] }}</div>
                        @endif
                        @if ($user === null)
                            <p><a class="js-user-link" href="#">Sign in</a> to ask for maps and review requests.</p>
                        @else
                            <form method="POST" action="{{ route('loved.requests.store') }}" class="tomyum-loved__form">
                                @csrf
                                <div class="tomyum-loved__form-row">
                                    <label class="input-container tomyum-loved__form-set">
                                        <span class="input-container__label">Beatmap set (link or id)</span>
                                        <input class="input-text" type="text" name="beatmapset" required value="{{ old('beatmapset') }}" placeholder="https://osu.ppy.sh/beatmapsets/…">
                                    </label>
                                    <label class="input-container input-container--select">
                                        <span class="input-container__label">Mode</span>
                                        <select class="input-text" name="mode">
                                            @foreach (TomYumLoved::MODE_NAMES as $id => $name)
                                                <option value="{{ $id }}" @selected((int) old('mode', Beatmap::MODES[$ruleset ?? default_mode()] ?? 0) === $id)>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                <label class="input-container">
                                    <span class="input-container__label">Why it deserves Loved</span>
                                    <textarea class="input-text" name="reason" rows="3" required minlength="10" maxlength="1000" placeholder="A classic from 2014 people still play">{{ old('reason') }}</textarea>
                                </label>
                                <p class="tomyum-loved__muted">
                                    Graveyard, WIP and pending maps only, one request per map and mode, up to {{ TomYumLoved::REQUESTS_PER_DAY }} a day.
                                    If someone asked for it already, you'll be sent to their request to review it.
                                </p>
                                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-heart"></i> Ask for it</button></div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @elseif ($loveStatus !== null)
            <div class="{{ class_with_modifiers('tomyum-loved__alert', [$loveStatus['type']]) }}">{{ $loveStatus['message'] }}</div>
        @endif

        <div class="page-extra">
            <h2 class="title title--page-extra">{{ $statusTitles[$status] }}{{ $modeName === null ? '' : " · {$modeName}" }}</h2>
            <div class="tomyum-loved__status">
                <span class="tomyum-loved__status-item">{{ $status === 'open' ? 'The best-reviewed first' : 'The latest first' }}</span>
                <span class="tomyum-loved__status-item">
                    @foreach (Beatmap::MODES as $mode => $modeId)
                        <a href="{{ route('loved.index', array_filter(['status' => $status === 'open' ? null : $status, 'mode' => $mode])) }}"
                            class="{{ class_with_modifiers('tomyum-loved__mode-count', ['active' => $mode === $ruleset]) }}"
                            title="{{ TomYumLoved::MODE_NAMES[$modeId] }}">
                            <span class="fal fa-extra-mode-{{ $mode }}"></span> {{ $counts[$modeId] ?? 0 }}
                        </a>
                    @endforeach
                </span>
            </div>

            @if ($items->isEmpty())
                <p class="tomyum-loved__muted">
                    {{ $status === 'open' ? 'Nobody asked for a map here yet. Be the first!' : 'Nothing here yet.' }}
                </p>
            @else
                <div class="tomyum-loved__entries">
                    @foreach ($items as $item)
                        @php
                            $request = $item['request'];
                            $url = route('loved.requests.show', ['request' => $request->getKey()]);
                        @endphp
                        <div class="tomyum-loved-entry" id="request-{{ $request->getKey() }}">
                            <div
                                class="js-react u-contents"
                                data-beatmapset-panel="{{ json_encode(['beatmapset' => $item['beatmapsetJson']]) }}"
                                data-react="beatmapset-panel"
                            >
                                <div class="beatmapset-panel beatmapset-panel--size-normal"></div>
                            </div>
                            <div class="tomyum-loved-entry__body">
                                <div class="tomyum-loved-entry__writeup">
                                    <div class="tomyum-loved-entry__captain">
                                        <span class="fal fa-extra-mode-{{ Beatmap::modeStr($request->ruleset_id) }}" title="{{ TomYumLoved::MODE_NAMES[$request->ruleset_id] }}"></span>
                                        Asked by
                                        @if ($request->user !== null)
                                            <a class="js-usercard" data-user-id="{{ $request->user_id }}" href="{{ route('users.show', ['user' => $request->user_id]) }}">{{ $request->user->username }}</a>
                                        @else
                                            someone
                                        @endif
                                        {!! timeago($request->created_at) !!}
                                    </div>
                                    <div class="tomyum-loved-entry__description">{{ $request->reason }}</div>
                                    @if ($status !== 'open' && present($request->decision_reason))
                                        <div class="tomyum-loved-entry__captain">
                                            <strong>{{ $request->handler?->username ?? 'The team' }}:</strong> {{ $request->decision_reason }}
                                        </div>
                                    @endif
                                </div>
                                <div class="tomyum-loved-entry__support">
                                    <span title="{{ $request->reviews_for + 1 }} for, {{ $request->reviews_against }} against">
                                        <strong>{{ $request->score > 0 ? '+' : '' }}{{ i18n_number_format($request->score) }}</strong>
                                        · {{ $request->reviews_for + 1 }} for · {{ $request->reviews_against }} against
                                    </span>
                                    <a class="{{ class_with_modifiers('tomyum-loved-entry__vote', ['support' => true]) }}" href="{{ $url }}">
                                        @if ($status === 'open')
                                            <i class="fas fa-comment"></i> Review
                                        @else
                                            <i class="fas fa-eye"></i> Open
                                        @endif
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @include('objects._pagination_v2', ['object' => $requests])
            @endif
        </div>

        @if ($status === 'open')
            <div class="page-extra">
                <h2 class="title title--page-extra">Questions</h2>
                @include('loved._faq')
            </div>

            @if (count($team) > 0)
                <div class="page-extra">
                    <h2 class="title title--page-extra">The Loved team</h2>
                    <p class="tomyum-loved__muted">They decide on the requests; their badge shows the modes they decide for.</p>
                    <div class="tomyum-loved__team">
                        @foreach ($team as $memberJson)
                            <div class="js-react u-contents" data-react="user-card" data-user="{{ json_encode($memberJson) }}"></div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection
