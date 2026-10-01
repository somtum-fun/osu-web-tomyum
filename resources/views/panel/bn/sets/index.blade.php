{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    use App\Http\Controllers\Panel\BnSetsController;
@endphp
@extends('master', ['titlePrepend' => 'Manage beatmap sets'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.bn.index'), 'title' => 'BN panel'],
        ['title' => 'Manage sets'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-search"></i>Find a beatmap set</h2>
            <form method="GET" action="{{ route('panel.bn.sets.index') }}" class="tomyum-panel__row">
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Title, artist, mapper, set id or link</span>
                    <input class="input-text" type="text" name="q" value="{{ $query }}" autofocus>
                </label>
                <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 180px">
                    <span class="input-container__label">Status</span>
                    <select class="input-text" name="status">
                        <option value="">Any</option>
                        @foreach (BnSetsController::APPROVED_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected((string) $status === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Search</button>
            </form>
            <p class="tomyum-panel__hint">
                Only sets on this server are listed. To add one, <a href="{{ route('panel.bn.index') }}">import it</a> first.
            </p>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-music"></i>{{ number_format($sets->total()) }} set(s)</h2>
            @if ($sets->count() > 0)
                <table class="tomyum-panel__table">
                    <tr><th></th><th>Set</th><th>Mapper</th><th>Difficulties</th><th>Status</th><th>Updated</th></tr>
                    @foreach ($sets as $set)
                        <tr>
                            <td style="width: 90px">
                                <img src="{{ $set->coverURL('list') }}" alt="" loading="lazy" style="border-radius: 4px; height: 45px; object-fit: cover; width: 80px">
                            </td>
                            <td>
                                <a href="{{ route('panel.bn.sets.show', ['set' => $set->getKey()]) }}">{{ $set->artist }} - {{ $set->title }}</a>
                                <div class="tomyum-panel__hint" style="margin: 0">#{{ $set->getKey() }}</div>
                            </td>
                            <td>{{ $set->creator }}</td>
                            <td>{{ $difficultyCounts[$set->getKey()] ?? 0 }}</td>
                            <td>@include('panel._status', ['approved' => $set->approved])</td>
                            <td>{!! timeago($set->last_update) !!}</td>
                        </tr>
                    @endforeach
                </table>
                <p class="tomyum-panel__row" style="justify-content: space-between">
                    <span>
                        @if ($sets->previousPageUrl())
                            <a href="{{ $sets->previousPageUrl() }}">&larr; Newer</a>
                        @endif
                    </span>
                    <span class="tomyum-panel__hint">Page {{ $sets->currentPage() }} of {{ $sets->lastPage() }}</span>
                    <span>
                        @if ($sets->nextPageUrl())
                            <a href="{{ $sets->nextPageUrl() }}">Older &rarr;</a>
                        @endif
                    </span>
                </p>
            @else
                <p class="tomyum-panel__empty">Nothing matches.</p>
            @endif
        </div>
    </div>
@endsection
