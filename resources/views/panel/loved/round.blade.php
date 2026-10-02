{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => $round->name])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.loved.index'), 'title' => 'Loved panel'],
        ['url' => route('panel.loved.index'), 'title' => 'Rounds'],
        ['title' => $round->name],
    ]])
    @php
        use App\Libraries\TomYumLoved;
        use App\Models\Beatmap;
    @endphp
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-heart"></i>{{ $round->name }}</h2>
            @if ($round->isPicking())
                <p class="tomyum-panel__hint">
                    Being picked: only the Loved team sees it. Add the maps below, then open voting: each map gets a topic
                    with a Yes/No poll in the Project Loved forum, posted by BanchoBot, and the round shows on
                    <a href="{{ route('loved.index') }}">the Project Loved page</a>.
                </p>
                <div class="tomyum-panel__row">
                    <form method="POST" action="{{ route('panel.loved.open', ['round' => $round->getKey()]) }}" class="tomyum-panel__row"
                        onsubmit="return confirm('Post the polls and open voting? Maps can\'t be added or taken out after.')">
                        @csrf
                        <label class="input-container tomyum-panel__field">
                            <span class="input-container__label">Voting runs for (days)</span>
                            <input class="input-text" type="number" name="days" min="1" max="60" value="7">
                        </label>
                        <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Open voting</button></div>
                    </form>
                    <form method="POST" action="{{ route('panel.loved.destroy', ['round' => $round->getKey()]) }}"
                        onsubmit="return confirm('Remove this round and its maps?')">
                        @csrf
                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove the round</button>
                    </form>
                </div>
            @elseif ($round->isVoting())
                <p class="tomyum-panel__hint">
                    Voting until {{ i18n_date($round->voting_ends_at) }}
                    (<a href="{{ route('loved.rounds.show', ['round' => $round->getKey()]) }}">the round's page</a>).
                    Closing ends the polls, posts each result in its topic and loves the maps that got
                    {{ $round->threshold }}% yes of at least {{ $round->min_votes }} votes. Polls that ran out are still counted.
                </p>
                <form method="POST" action="{{ route('panel.loved.close', ['round' => $round->getKey()]) }}"
                    onsubmit="return confirm('Close the round now and love the maps that passed?')">
                    @csrf
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Close the round</button>
                </form>
            @else
                <p class="tomyum-panel__hint">
                    Closed {{ i18n_date($round->closed_at) }}: {{ $nominations->where('passed', true)->count() }} of {{ $nominations->count() }} maps passed
                    (<a href="{{ route('loved.rounds.show', ['round' => $round->getKey()]) }}">results</a>).
                </p>
            @endif

            @if (!$round->isClosed())
                <h3 class="tomyum-panel__section-title">Settings</h3>
                <form method="POST" action="{{ route('panel.loved.update', ['round' => $round->getKey()]) }}" class="tomyum-panel__row">
                    @csrf
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Name</span>
                        <input class="input-text" type="text" name="name" required maxlength="100" value="{{ $round->name }}">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Yes votes needed (%)</span>
                        <input class="input-text" type="number" name="threshold" min="1" max="100" value="{{ $round->threshold }}">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Fewest votes</span>
                        <input class="input-text" type="number" name="min_votes" min="1" max="10000" value="{{ $round->min_votes }}">
                    </label>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button></div>
                </form>
            @endif
        </div>

        @if ($round->isPicking())
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-plus"></i>Add a map</h2>
                @if (count($captainModes) === 0)
                    <p class="tomyum-panel__hint">You don't pick maps for any mode. An admin can give you modes with your Project Loved group.</p>
                @else
                    <p class="tomyum-panel__hint">A graveyard, WIP or pending set on this server (import it in the BN panel if it isn't). The write-up shows on the round's page and in the poll's topic.</p>
                    <form method="POST" action="{{ route('panel.loved.nominate', ['round' => $round->getKey()]) }}">
                        @csrf
                        <div class="tomyum-panel__row">
                            <label class="input-container tomyum-panel__field">
                                <span class="input-container__label">Set (link or id)</span>
                                <input class="input-text" type="text" name="beatmapset" required placeholder="https://osu.ppy.sh/beatmapsets/…">
                            </label>
                            <label class="input-container input-container--select tomyum-panel__field">
                                <span class="input-container__label">Mode</span>
                                <select class="input-text" name="mode">
                                    @foreach ($captainModes as $mode)
                                        <option value="{{ $mode }}">{{ TomYumLoved::MODE_NAMES[$mode] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label class="input-container">
                            <span class="input-container__label">Why it deserves Loved (BBCode)</span>
                            <textarea class="input-text" name="description" rows="4"></textarea>
                        </label>
                        <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Add it</button></div>
                    </form>
                @endif
            </div>
        @endif

        @if ($round->isPicking() && count($captainModes) > 0)
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-inbox"></i>Requests <span class="tomyum-panel__muted">({{ $requests->count() }})</span></h2>
                <p class="tomyum-panel__hint">
                    What players asked for on <a href="{{ route('loved.requests') }}">the requests page</a>, in your modes, the most supported first.
                    Adding one puts it in this round; declining tells players why (they see it, and can't ask for it again for 90 days).
                </p>
                @if ($requests->isEmpty())
                    <p class="tomyum-panel__empty">No open requests.</p>
                @else
                    <div class="tomyum-loved-picks">
                        @foreach ($requests as $request)
                            @php
                                $set = $request->beatmapset;
                            @endphp
                            <div class="tomyum-loved-pick">
                                @if ($set === null)
                                    <span class="tomyum-loved-pick__cover"></span>
                                @else
                                    <a class="tomyum-loved-pick__cover" href="{{ route('beatmapsets.show', ['beatmapset' => $set->getKey()]) }}" style="background-image: url('{{ $set->coverURL('list') }}')"></a>
                                @endif
                                <div class="tomyum-loved-pick__info">
                                    <span class="tomyum-loved-pick__title">{{ $set?->title ?? "Set {$request->beatmapset_id}" }}</span>
                                    <div class="tomyum-loved-pick__meta">
                                        {{ $set?->artist }} · mapped by {{ $set?->creator }} · {{ $set?->status() }} ·
                                        <span class="fal fa-extra-mode-{{ Beatmap::modeStr($request->ruleset_id) }}"></span> {{ TomYumLoved::MODE_NAMES[$request->ruleset_id] }}
                                    </div>
                                    <div class="tomyum-loved-pick__description">
                                        {{ $request->reason }}
                                        <span class="tomyum-panel__muted">— {{ $request->user?->username ?? 'someone' }}</span>
                                    </div>
                                    <details class="tomyum-loved-pick__edit">
                                        <summary>Decline</summary>
                                        <form method="POST" action="{{ route('panel.loved.requests.decline', ['round' => $round->getKey(), 'request' => $request->getKey()]) }}">
                                            @csrf
                                            <input class="input-text" type="text" name="reason" required maxlength="500" placeholder="Why (players see this)" style="width: 100%">
                                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Decline</button>
                                        </form>
                                    </details>
                                </div>
                                <div class="tomyum-loved-pick__side">
                                    <div><strong>{{ $request->supporters_count }}</strong> <span class="tomyum-panel__muted">{{ $request->supporters_count === 1 ? 'supporter' : 'supporters' }}</span></div>
                                    <form method="POST" action="{{ route('panel.loved.requests.pick', ['round' => $round->getKey(), 'request' => $request->getKey()]) }}">
                                        @csrf
                                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Add to round</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @foreach (TomYumLoved::MODE_NAMES as $mode => $modeName)
            @php
                $inMode = $nominations->where('ruleset_id', $mode);
            @endphp
            @continue($inMode->isEmpty())
            <div class="tomyum-panel__card">
                <h2><span class="fal fa-extra-mode-{{ Beatmap::modeStr($mode) }}"></span> {{ $modeName }} <span class="tomyum-panel__muted">({{ $inMode->count() }})</span></h2>
                <div class="tomyum-loved-picks">
                    @foreach ($inMode as $nomination)
                        @php
                            $set = $nomination->beatmapset;
                            [$yes, $no] = TomYumLoved::votes($nomination);
                            $total = $yes + $no;
                            $canEdit = $round->isPicking() && in_array($mode, $captainModes, true);
                            $setUrl = $set === null ? null : route('beatmapsets.show', ['beatmapset' => $set->getKey()]);
                        @endphp
                        <div class="tomyum-loved-pick" id="nomination-{{ $nomination->getKey() }}">
                            @if ($set === null)
                                <span class="tomyum-loved-pick__cover"></span>
                            @else
                                <a class="tomyum-loved-pick__cover" href="{{ $setUrl }}" style="background-image: url('{{ $set->coverURL('list') }}')"></a>
                            @endif
                            <div class="tomyum-loved-pick__info">
                                @if ($set === null)
                                    <span class="tomyum-loved-pick__title">Set {{ $nomination->beatmapset_id }} (gone)</span>
                                @else
                                    <a class="tomyum-loved-pick__title" href="{{ $setUrl }}">{{ $set->title }}</a>
                                    <div class="tomyum-loved-pick__meta">
                                        {{ $set->artist }} · mapped by {{ $set->creator }} · {{ $set->status() }} · picked by {{ $nomination->captain?->username ?? '?' }}
                                    </div>
                                @endif
                                @if ($nomination->descriptionHTML() !== null)
                                    <div class="tomyum-loved-pick__description">{!! $nomination->descriptionHTML() !!}</div>
                                @elseif ($canEdit)
                                    <div class="tomyum-loved-pick__description tomyum-loved-pick__description--empty">No write-up yet.</div>
                                @endif
                                @if ($canEdit)
                                    <details class="tomyum-loved-pick__edit">
                                        <summary>Edit write-up</summary>
                                        <form method="POST" action="{{ route('panel.loved.nominations.update', ['nomination' => $nomination->getKey()]) }}">
                                            @csrf
                                            <textarea class="input-text" name="description" rows="4" placeholder="Why it deserves Loved (BBCode)">{{ $nomination->description }}</textarea>
                                            <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                            <div class="tomyum-loved-pick__side">
                                @if ($nomination->topic_id !== null)
                                    <div>
                                        <strong>{{ $total === 0 ? '—' : round($yes * 100 / $total, 1).'%' }}</strong>
                                        <span class="tomyum-panel__muted">yes ({{ $yes }}:{{ $no }})</span>
                                    </div>
                                    @if ($nomination->passed !== null)
                                        <span class="tomyum-panel__pill tomyum-panel__pill--{{ $nomination->passed ? 'good' : 'bad' }}">{{ $nomination->passed ? 'passed' : 'failed' }}</span>
                                    @endif
                                    <a href="{{ route('forum.topics.show', ['topic' => $nomination->topic_id]) }}">topic <i class="fas fa-comments"></i></a>
                                @endif
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('panel.loved.nominations.destroy', ['nomination' => $nomination->getKey()]) }}"
                                        onsubmit="return confirm('Take this map out of the round?')">
                                        @csrf
                                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Take out</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        @if ($nominations->isEmpty())
            <div class="tomyum-panel__card"><p class="tomyum-panel__empty">No maps in this round yet.</p></div>
        @endif
    </div>
@endsection
