{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the changelog panel (admins and developers): streams and their versions. --}}
@extends('master', ['titlePrepend' => 'Changelog'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.index'), 'title' => 'Staff panel'],
        ['title' => 'Changelog'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>New version</h2>
            <p class="tomyum-panel__hint">
                A version holds the changes players read on the <a href="{{ route('changelog.index') }}">changelog</a> (and in lazer, if your build reads it).
                Leave the version empty for the next one of the day (like {{ App\Libraries\TomYumChangelog::nextVersion(6, Carbon\Carbon::now()) }}),
                or write your own (1.4.0, b20150203-patched.3). Hidden versions aren't shown until you untick it.
            </p>
            {{-- TomYum: the fields on panel-changelog__form's grid, the tick and button on a row below them. --}}
            <form method="POST" action="{{ route('panel.changelog.builds.store') }}" class="panel-changelog__form panel-changelog__form--3">
                @csrf
                <label class="input-container input-container--select">
                    <span class="input-container__label">Stream</span>
                    <select class="input-text" name="stream_id">
                        @foreach ($streams as $stream)
                            <option value="{{ $stream->getKey() }}" @if ($streamId === $stream->getKey()) selected @endif>{{ $stream->pretty_name ?? $stream->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="input-container">
                    <span class="input-container__label">Version (empty: next of the day)</span>
                    <input class="input-text" type="text" name="version" maxlength="40">
                </label>
                <label class="input-container">
                    <span class="input-container__label">Date (UTC; empty: now)</span>
                    <input class="input-text" type="text" name="date" placeholder="{{ Carbon\Carbon::now()->format('Y-m-d H:i') }}">
                </label>
                <div class="panel-changelog__actions panel-changelog__wide">
                    <label class="tomyum-panel__check"><input type="checkbox" name="private" value="1" checked> Hidden for now</label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-plus"></i>Add version</button>
                </div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-code-branch"></i>Versions</h2>
            <div class="panel-changelog__streams">
                <a class="{{ class_with_modifiers('tomyum-panel__pill', ['info' => $streamId === null]) }}" href="{{ route('panel.changelog.index') }}">All</a>
                @foreach ($streams as $stream)
                    <a class="{{ class_with_modifiers('tomyum-panel__pill', ['info' => $streamId === $stream->getKey()]) }}" href="{{ route('panel.changelog.index', ['stream' => $stream->getKey()]) }}">{{ $stream->pretty_name ?? $stream->name }}</a>
                @endforeach
            </div>
            @if (count($builds) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <div class="tomyum-panel__scroll">
                    <table class="tomyum-panel__table">
                        <tr><th>Version</th><th>Stream</th><th>Date</th><th>Changes</th><th></th></tr>
                        @foreach ($builds as $build)
                            <tr>
                                <td>
                                    <a href="{{ route('panel.changelog.builds.show', ['build' => $build->getKey()]) }}">{{ $build->version }}</a>
                                    @if ($build->private) <span class="tomyum-panel__pill tomyum-panel__pill--warn">hidden</span> @endif
                                    @if ($build->hash !== null) <span class="tomyum-panel__pill" title="Made by tomyum:changelog-import from git commits">from git</span> @endif
                                </td>
                                <td>{{ $streams[$build->stream_id]->pretty_name ?? $build->stream_id }}</td>
                                <td class="tomyum-panel__nowrap">{{ $build->date?->format('Y-m-d H:i') }}</td>
                                <td>{{ $build->changelog_entries_count }}</td>
                                <td class="tomyum-panel__nowrap">
                                    <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.changelog.builds.show', ['build' => $build->getKey()]) }}">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                @include('objects._pagination_v2', ['object' => $builds])
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-stream"></i>Streams</h2>
            <p class="tomyum-panel__hint">
                The changelog's tabs. Which are shown, and in what order, is <code>UPDATE_STREAMS</code> on the website
                (a stream also needs at least one shown version before its tab appears).
            </p>
            <div class="tomyum-panel__scroll">
                <table class="tomyum-panel__table">
                    <tr><th class="panel-changelog__id">Id</th><th>Name</th><th>Shown</th><th>Name on the page</th></tr>
                    @foreach ($streams as $stream)
                        <tr>
                            <td class="panel-changelog__id">{{ $stream->getKey() }}</td>
                            <td><code>{{ $stream->name }}</code></td>
                            <td>
                                @if (in_array($stream->getKey(), $shown, true))
                                    <span class="tomyum-panel__pill tomyum-panel__pill--good">yes</span>
                                @else
                                    <span class="tomyum-panel__pill tomyum-panel__pill--bad">not in UPDATE_STREAMS</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('panel.changelog.streams.update', ['stream' => $stream->getKey()]) }}" class="panel-changelog__rename">
                                    @csrf
                                    <label class="input-container">
                                        <input class="input-text" type="text" name="pretty_name" maxlength="50" value="{{ $stream->pretty_name }}" required aria-label="Name on the page">
                                    </label>
                                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Rename</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
@endsection
