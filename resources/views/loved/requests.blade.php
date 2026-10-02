{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: Project Loved requests (LovedController@requests): ask for a map, support others' requests. --}}
@php
    use App\Libraries\TomYumLoved;
    use App\Models\Beatmap;

    $rulesetId = Beatmap::MODES[$ruleset];
    $modeName = TomYumLoved::MODE_NAMES[$rulesetId];
    $status = session('loved_status');
    $user = Auth::user();
@endphp
@extends('master', ['titlePrepend' => 'Requests'])

@section('content')
    @include('loved._header', [
        'current' => 'requests',
        'urlFn' => fn ($mode) => route('loved.requests', ['mode' => $mode]),
    ])

    <div class="osu-page osu-page--generic-compact tomyum-loved">
        <div class="page-extra">
            <h2 class="title title--page-extra">Request a map</h2>
            <div class="tomyum-loved__about">
                <div>
                    <p>
                        Ask for a map you'd like to see Loved: paste its link (from here or osu.ppy.sh), pick the mode and
                        say why it deserves it. If someone asked for it already, you support their request instead.
                    </p>
                    <p class="tomyum-loved__muted">
                        Graveyard, WIP and pending maps only, up to {{ TomYumLoved::REQUESTS_PER_DAY }} requests a day.
                        The Loved team picks each round's maps from here, the most supported first, and may decline a request with a reason.
                    </p>
                </div>
                <div>
                    @if ($status !== null)
                        <div class="{{ class_with_modifiers('tomyum-loved__alert', [$status['type']]) }}">{{ $status['message'] }}</div>
                    @endif
                    @if ($user === null)
                        <p>
                            <a class="js-user-link" href="#">Sign in</a> to request maps and support requests.
                        </p>
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
                                            <option value="{{ $id }}" @if ((int) old('mode', $rulesetId) === $id) selected @endif>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <label class="input-container">
                                <span class="input-container__label">Why it deserves Loved</span>
                                <textarea class="input-text" name="reason" rows="3" required minlength="10" maxlength="1000" placeholder="A classic from 2014 people still play">{{ old('reason') }}</textarea>
                            </label>
                            <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-heart"></i> Request it</button></div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="page-extra">
            <h2 class="title title--page-extra">Requested for {{ $modeName }}</h2>
            <div class="tomyum-loved__status">
                <span class="tomyum-loved__status-item">Most supported first</span>
                @if ($round !== null)
                    <span class="tomyum-loved__status-item tomyum-loved__status-item--live">
                        <i class="fas fa-circle"></i> <a href="{{ route('loved.rounds.show', ['round' => $round->getKey(), 'mode' => $ruleset]) }}">{{ $round->name }}</a> is voting now
                    </span>
                @endif
                <span class="tomyum-loved__status-item">
                    @foreach (Beatmap::MODES as $mode => $modeId)
                        <a href="{{ route('loved.requests', ['mode' => $mode]) }}"
                            class="{{ class_with_modifiers('tomyum-loved__mode-count', ['active' => $modeId === $rulesetId]) }}"
                            title="{{ TomYumLoved::MODE_NAMES[$modeId] }}">
                            <span class="fal fa-extra-mode-{{ $mode }}"></span> {{ $counts[$modeId] ?? 0 }}
                        </a>
                    @endforeach
                </span>
            </div>

            @if ($open->isEmpty())
                <p class="tomyum-loved__muted">Nobody asked for a {{ $modeName }} map yet. Be the first!</p>
            @else
                <div class="tomyum-loved__entries">
                    @foreach ($open as $item)
                        @php
                            $request = $item['request'];
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
                                        Requested by
                                        @if ($request->user !== null)
                                            <a class="js-usercard" data-user-id="{{ $request->user_id }}" href="{{ route('users.show', ['user' => $request->user_id]) }}">{{ $request->user->username }}</a>
                                        @else
                                            someone
                                        @endif
                                        {!! timeago($request->created_at) !!}
                                    </div>
                                    <div class="tomyum-loved-entry__description">{{ $request->reason }}</div>
                                </div>
                                <div class="tomyum-loved-entry__support">
                                    <span>
                                        <strong>{{ i18n_number_format($request->supporters_count) }}</strong>
                                        {{ $request->supporters_count === 1 ? 'supporter' : 'supporters' }}
                                    </span>
                                    @if ($user !== null)
                                        <form method="POST" action="{{ route('loved.requests.support', ['request' => $request->getKey()]) }}">
                                            @csrf
                                            <input type="hidden" name="support" value="{{ $item['supported'] ? '0' : '1' }}">
                                            <button type="submit"
                                                class="{{ class_with_modifiers('tomyum-loved-entry__vote', ['active' => $item['supported'], 'support' => true]) }}"
                                                title="{{ $item['supported'] ? 'Take your support back' : 'Support this request' }}">
                                                <i class="fas fa-heart"></i> {{ $item['supported'] ? 'Supported' : 'Support' }}
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

        @if ($declined->isNotEmpty())
            <div class="page-extra">
                <h2 class="title title--page-extra">Declined lately</h2>
                <p class="tomyum-loved__muted">These can't be asked for again for a while.</p>
                <ul class="tomyum-loved__declined">
                    @foreach ($declined as $request)
                        <li>
                            @if ($request->beatmapset !== null)
                                <a href="{{ route('beatmapsets.show', ['beatmapset' => $request->beatmapset_id]) }}">{{ $request->beatmapset->artist }} - {{ $request->beatmapset->title }}</a>
                            @else
                                Set {{ $request->beatmapset_id }}
                            @endif
                            <span class="tomyum-loved__muted">
                                · {{ $request->decline_reason }}
                                @if ($request->handler !== null)
                                    ({{ $request->handler->username }}, {!! timeago($request->updated_at) !!})
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection
