{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: one contest: settings, uploads, entries and results, judges (contests panel). --}}
@php
    $phases = ['preparing' => 'not open yet', 'entry' => 'taking entries', 'voting' => 'voting', 'results' => 'results out', 'over' => 'over'];
    $judged = $contest->isJudged();
    $scoreOf = function ($entry) use ($contest, $judged) {
        if ($contest->isBestOf()) {
            return $entry->votes_count ?? 0;
        }
        if ($judged) {
            return $contest->isScoreStandardised()
                ? number_format((float) ($entry->total_score_std ?? 0), 2)
                : ($entry->scores_sum_value ?? 0);
        }

        return $entry->votes_count ?? 0;
    };
@endphp
@extends('master', ['titlePrepend' => "Contest: {$contest->name}"])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['url' => route('panel.admin.contests.index'), 'title' => 'Contests'],
        ['title' => $contest->name],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2>
                <i class="fas fa-award"></i>{{ $contest->name }}
                <span class="tomyum-panel__pill tomyum-panel__pill--info">{{ $phases[$contest->state()] ?? $contest->state() }}</span>
                @if (!$contest->visible) <span class="tomyum-panel__pill tomyum-panel__pill--warn">hidden</span> @endif
            </h2>
            <p><a href="{{ $contest->url() }}">Open the contest page</a> &middot; {{ $votesCount }} votes from {{ $votersCount }} players</p>
            @include('panel.admin.contests._form')
        </div>

        @if ($contest->type !== 'external')
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-upload"></i>Uploads ({{ count($submissions->whereNull('deleted_at')) }})</h2>
                <p class="tomyum-panel__hint">
                    What players uploaded. When entries close, turn them into the entries people vote on: each gets a random
                    "Entry #n" so voters don't know whose it is (ticked ones only, or all of them if none is ticked).
                    Remove or restore uploads, or download them all as a zip, on <a href="{{ route('admin.contests.show', ['contest' => $contest->getKey()]) }}">the contest's admin page</a>.
                </p>
                @if (count($submissions) === 0)
                    <p class="tomyum-panel__empty">None yet.</p>
                @else
                    <form method="POST" action="{{ route('panel.admin.contests.entries.make', ['contest' => $contest->getKey()]) }}">
                        @csrf
                        <table class="tomyum-panel__table">
                            <tr><th></th><th>File</th><th>Player</th><th>Size</th><th>Uploaded</th></tr>
                            @foreach ($submissions as $submission)
                                @php $url = $submission->file()->url(); @endphp
                                <tr>
                                    <td>
                                        @if ($submission->deleted_at === null && $url !== null && !$madeUrls->has($url))
                                            <input type="checkbox" name="submissions[]" value="{{ $submission->getKey() }}">
                                        @endif
                                    </td>
                                    <td>
                                        @if ($url !== null) <a href="{{ $url }}">{{ $submission->original_filename }}</a> @else {{ $submission->original_filename }} @endif
                                        @if ($submission->deleted_at !== null) <span class="tomyum-panel__pill tomyum-panel__pill--bad">removed</span> @endif
                                        @if ($url !== null && $madeUrls->has($url)) <span class="tomyum-panel__pill tomyum-panel__pill--good">entry</span> @endif
                                    </td>
                                    <td>@if ($submission->user !== null)<a href="{{ route('users.show', ['user' => $submission->user_id]) }}">{{ $submission->user->username }}</a>@else #{{ $submission->user_id }} @endif</td>
                                    <td class="tomyum-panel__nowrap">{{ number_format(($submission->filesize ?? 0) / 1048576, 1) }} MB</td>
                                    <td class="tomyum-panel__nowrap">{{ $submission->created_at?->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </table>
                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit" style="margin-top: 8px"
                            onclick="return confirm('Make voting entries from these uploads?')"><i class="fas fa-magic"></i>Make voting entries</button>
                    </form>
                @endif
            </div>
        @endif

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-poll"></i>Entries and results</h2>
            <p class="tomyum-panel__hint">
                What people vote on, best first ({{ $judged ? 'by the judges\' scores' : 'by votes' }}). Players see the results when
                "Show the results" is ticked. An entry people voted for can't be removed.
            </p>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>#</th><th>Name</th><th>Shown as</th><th>By</th><th>{{ $judged ? 'Score' : 'Votes' }}</th><th></th></tr>
                    @foreach ($entries as $index => $entry)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>@if (present($entry->entry_url))<a href="{{ $entry->entry_url }}">{{ $entry->name }}</a>@else {{ $entry->name }} @endif</td>
                            <td>{{ $entry->masked_name }}</td>
                            <td>{{ $entry->user?->username ?? '' }}</td>
                            <td>{{ $scoreOf($entry) }}</td>
                            <td class="tomyum-panel__nowrap">
                                <details style="display: inline-block">
                                    <summary class="btn-osu-big btn-osu-big--rounded-thin">Edit</summary>
                                    <form method="POST" action="{{ route('panel.admin.contests.entries.store', ['contest' => $contest->getKey()]) }}" style="min-width: 320px">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $entry->getKey() }}">
                                        <input class="input-text" type="text" name="name" maxlength="255" required value="{{ $entry->name }}" placeholder="Name">
                                        <input class="input-text" type="text" name="masked_name" maxlength="255" value="{{ $entry->masked_name }}" placeholder="Shown as">
                                        <input class="input-text" type="text" name="entry_url" maxlength="255" value="{{ $entry->entry_url }}" placeholder="Link">
                                        <input class="input-text" type="text" name="thumbnail_url" maxlength="255" value="{{ $entry->thumbnail_url }}" placeholder="Thumbnail link">
                                        <input class="input-text" type="text" name="user" value="{{ $entry->user?->username }}" placeholder="Made by (player)">
                                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('panel.admin.contests.entries.destroy', ['contest' => $contest->getKey(), 'entry' => $entry->getKey()]) }}" style="display: inline"
                                    onsubmit="return confirm('Remove this entry?')">
                                    @csrf
                                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
            <h3>Add an entry by hand</h3>
            <form method="POST" action="{{ route('panel.admin.contests.entries.store', ['contest' => $contest->getKey()]) }}" class="tomyum-panel__row" style="flex-wrap: wrap; align-items: flex-end">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Name</span>
                    <input class="input-text" type="text" name="name" maxlength="255" required>
                </label>
                <label class="input-container tomyum-panel__field" style="flex: 0 1 160px">
                    <span class="input-container__label">Shown as (empty: Entry #n)</span>
                    <input class="input-text" type="text" name="masked_name" maxlength="255">
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Link</span>
                    <input class="input-text" type="text" name="entry_url" maxlength="255" placeholder="https://…">
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Thumbnail (art; optional)</span>
                    <input class="input-text" type="text" name="thumbnail_url" maxlength="255" placeholder="https://…">
                </label>
                <label class="input-container tomyum-panel__field" style="flex: 0 1 160px">
                    <span class="input-container__label">Made by (optional)</span>
                    <input class="input-text" type="text" name="user">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-plus"></i>Add</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-gavel"></i>Judges</h2>
            <p class="tomyum-panel__hint">For judged contests: judges score each entry in every category on the contest page while voting is open.</p>
            @if (count($judges) === 0)
                <p class="tomyum-panel__empty">None.</p>
            @else
                <table class="tomyum-panel__table">
                    @foreach ($judges as $judge)
                        <tr>
                            <td><a href="{{ route('users.show', ['user' => $judge->getKey()]) }}">{{ $judge->username }}</a></td>
                            <td class="tomyum-panel__nowrap">
                                <form method="POST" action="{{ route('panel.admin.contests.judges.destroy', ['contest' => $contest->getKey(), 'user' => $judge->getKey()]) }}" style="display: inline">
                                    @csrf
                                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
            <form method="POST" action="{{ route('panel.admin.contests.judges.store', ['contest' => $contest->getKey()]) }}" class="tomyum-panel__row" style="align-items: flex-end">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Player (name or id)</span>
                    <input class="input-text" type="text" name="user" required>
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-plus"></i>Add judge</button>
            </form>

            <h3>Scoring categories</h3>
            @foreach ($categories as $category)
                <form method="POST" action="{{ route('panel.admin.contests.categories.store', ['contest' => $contest->getKey()]) }}" class="tomyum-panel__row" style="align-items: flex-end">
                    @csrf
                    <input type="hidden" name="id" value="{{ $category->getKey() }}">
                    <input class="input-text" type="text" name="name" maxlength="255" required value="{{ $category->name }}">
                    <input class="input-text" type="text" name="description" maxlength="255" value="{{ $category->description }}" placeholder="What judges look at">
                    <input class="input-text" type="number" name="max_value" min="1" max="127" value="{{ $category->max_value }}" style="max-width: 90px">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button>
                </form>
                <form method="POST" action="{{ route('panel.admin.contests.categories.destroy', ['contest' => $contest->getKey(), 'category' => $category->getKey()]) }}" style="margin-bottom: 6px">
                    @csrf
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove "{{ $category->name }}"</button>
                </form>
            @endforeach
            <form method="POST" action="{{ route('panel.admin.contests.categories.store', ['contest' => $contest->getKey()]) }}" class="tomyum-panel__row" style="align-items: flex-end">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">New category</span>
                    <input class="input-text" type="text" name="name" maxlength="255" required placeholder="e.g. Creativity">
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Description</span>
                    <input class="input-text" type="text" name="description" maxlength="255">
                </label>
                <label class="input-container tomyum-panel__field" style="flex: 0 1 110px">
                    <span class="input-container__label">Top score</span>
                    <input class="input-text" type="number" name="max_value" min="1" max="127" value="10">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-plus"></i>Add</button>
            </form>
            @if ($judged && $contest->isScoreStandardised())
                <form method="POST" action="{{ route('panel.admin.contests.calculate', ['contest' => $contest->getKey()]) }}" style="margin-top: 10px">
                    @csrf
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-calculator"></i>Calculate standardised scores</button>
                </form>
            @endif
        </div>

        <div class="tomyum-panel__card tomyum-panel__card--danger">
            <h2><i class="fas fa-trash"></i>Remove the contest</h2>
            <p class="tomyum-panel__hint">Only a contest nobody entered or voted in can be removed; otherwise hide it.</p>
            <form method="POST" action="{{ route('panel.admin.contests.destroy', ['contest' => $contest->getKey()]) }}" onsubmit="return confirm('Remove this contest for good?')">
                @csrf
                <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove contest</button>
            </form>
        </div>
    </div>
@endsection
