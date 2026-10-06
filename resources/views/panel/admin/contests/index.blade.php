{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the contests panel (admins). --}}
@php
    $phases = ['preparing' => 'not open yet', 'entry' => 'taking entries', 'voting' => 'voting', 'results' => 'results out', 'over' => 'over'];
@endphp
@extends('master', ['titlePrepend' => 'Contests'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Contests'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Make a contest</h2>
            <p class="tomyum-panel__hint">
                Art, beatmap and music contests take uploads while entries are open; then turn the uploads into entries to vote on.
                External contests have entries you add by hand (links). New contests start hidden. Players may vote once they have
                at least one ranked play and no restriction or silence.
            </p>
            @include('panel.admin.contests._form', ['contest' => null, 'otherOptions' => []])
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-award"></i>Contests</h2>
            @if (count($contests) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>#</th><th>Name</th><th>Kind</th><th>Now</th><th>Uploads</th><th>Entries</th><th>Votes</th><th></th></tr>
                    @foreach ($contests as $contest)
                        @php $protected = App\Http\Controllers\Panel\ContestsController::isProtected($contest->getKey()); @endphp
                        <tr>
                            <td>{{ $contest->getKey() }}</td>
                            <td>
                                <a href="{{ $contest->url() }}">{{ $contest->name }}</a>
                                @if (!$contest->visible) <span class="tomyum-panel__pill tomyum-panel__pill--warn">hidden</span> @endif
                            </td>
                            <td>{{ $types[$contest->type] ?? $contest->type }}</td>
                            <td>{{ $protected ? 'seasonal backgrounds' : ($phases[$contest->state()] ?? $contest->state()) }}</td>
                            <td>{{ $contest->user_contest_entries_count }}</td>
                            <td>{{ $contest->entries_count }}</td>
                            <td>{{ $contest->votes_count }}</td>
                            <td class="tomyum-panel__nowrap">
                                @if ($protected)
                                    <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.admin.seasonal-backgrounds.index') }}">Backgrounds</a>
                                @else
                                    <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.admin.contests.show', ['contest' => $contest->getKey()]) }}">Manage</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
@endsection
