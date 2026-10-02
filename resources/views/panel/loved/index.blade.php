{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Project Loved'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.loved.index'), 'title' => 'Loved panel'],
        ['title' => 'Rounds'],
    ]])
    @php
        $statePill = ['picking' => ['info', 'picking maps'], 'voting' => ['good', 'voting'], 'closed' => ['', 'closed']];
        $running = $rounds->first(fn ($round) => !$round->isClosed());
    @endphp
    <div class="osu-page osu-page--admin tomyum-panel">
        @if ($running === null)
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-plus"></i>Start a round</h2>
                <p class="tomyum-panel__hint">
                    Captains add maps for their modes, then voting opens: each map gets a topic with a Yes/No poll in the
                    <a href="{{ route('forum.forums.show', ['forum' => $GLOBALS['cfg']['tomyum']['loved']['forum_id']]) }}">Project Loved forum</a>.
                    When the round closes, the maps with enough votes, and enough of them yes, are Loved.
                </p>
                <form method="POST" action="{{ route('panel.loved.store') }}" class="tomyum-panel__row">
                    @csrf
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Name</span>
                        <input class="input-text" type="text" name="name" required maxlength="100" value="Round #{{ $rounds->count() + 1 }}">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Yes votes needed (%)</span>
                        <input class="input-text" type="number" name="threshold" min="1" max="100" value="{{ $rounds->first()?->threshold ?? 70 }}">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Fewest votes</span>
                        <input class="input-text" type="number" name="min_votes" min="1" max="10000" value="{{ $rounds->first()?->min_votes ?? 10 }}">
                    </label>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Start it</button></div>
                </form>
            </div>
        @endif

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-heart"></i>Rounds</h2>
            @if (count($rounds) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Round</th><th>State</th><th>Maps</th><th>Passes with</th><th>Voting ends</th></tr>
                    @foreach ($rounds as $round)
                        @php
                            [$pill, $label] = $statePill[$round->state];
                        @endphp
                        <tr>
                            <td><a href="{{ route('panel.loved.show', ['round' => $round->getKey()]) }}">{{ $round->name }}</a></td>
                            <td><span class="tomyum-panel__pill @if ($pill !== '') tomyum-panel__pill--{{ $pill }} @endif">{{ $label }}</span></td>
                            <td>{{ $round->nominations_count }}</td>
                            <td>{{ $round->threshold }}% of {{ $round->min_votes }}+ votes</td>
                            <td>{!! $round->voting_ends_at === null ? '<span class="tomyum-panel__muted">—</span>' : e(i18n_date($round->voting_ends_at)) !!}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
@endsection
