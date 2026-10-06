{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: one changelog version and its changes (changelog panel). --}}
@php
    $streamName = $streams[$build->stream_id]->pretty_name ?? $streams[$build->stream_id]->name ?? '?';
    $credit = function ($entry) {
        if ($entry->githubUser !== null) {
            return $entry->githubUser->displayUsername().' (GitHub)';
        }

        return $entry->tomyumUser?->username ?? $entry->tomyum_author;
    };
@endphp
@extends('master', ['titlePrepend' => "Changelog: {$streamName} {$build->version}"])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.index'), 'title' => 'Staff panel'],
        ['url' => route('panel.changelog.index'), 'title' => 'Changelog'],
        ['title' => "{$streamName} {$build->version}"],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2>
                <i class="fas fa-code-branch"></i>{{ $streamName }} {{ $build->version }}
                @if ($build->private) <span class="tomyum-panel__pill tomyum-panel__pill--warn">hidden</span> @endif
            </h2>
            @if (!$build->private)
                <p><a href="{{ $build->url() }}">See it on the changelog</a></p>
            @endif
            <form method="POST" action="{{ route('panel.changelog.builds.store') }}" class="tomyum-panel__row" style="align-items: flex-end; flex-wrap: wrap">
                @csrf
                <input type="hidden" name="id" value="{{ $build->getKey() }}">
                <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 200px">
                    <span class="input-container__label">Stream</span>
                    <select class="input-text" name="stream_id">
                        @foreach ($streams as $stream)
                            <option value="{{ $stream->getKey() }}" @if ($build->stream_id === $stream->getKey()) selected @endif>{{ $stream->pretty_name ?? $stream->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                    <span class="input-container__label">Version</span>
                    <input class="input-text" type="text" name="version" maxlength="40" value="{{ $build->version }}" required>
                </label>
                <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                    <span class="input-container__label">Date (UTC)</span>
                    <input class="input-text" type="text" name="date" value="{{ $build->date?->format('Y-m-d H:i') }}">
                </label>
                <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                    <span class="input-container__label">YouTube video id (optional)</span>
                    <input class="input-text" type="text" name="youtube_id" maxlength="20" value="{{ $build->youtube_id }}">
                </label>
                <label class="tomyum-panel__check"><input type="checkbox" name="private" value="1" @if ($build->private) checked @endif> Hidden</label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
            </form>
            <form method="POST" action="{{ route('panel.changelog.builds.destroy', ['build' => $build->getKey()]) }}" style="margin-top: 8px"
                onsubmit="return confirm('Remove this version and its changes for good?')">
                @csrf
                <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove version</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-pen"></i>{{ $editing === null ? 'Add a change' : 'Edit "'.$editing->title.'"' }}</h2>
            <p class="tomyum-panel__hint">
                The title is one line (`code` works in it); the message is optional and in markdown (**bold**, [links](https://…), lists; HTML isn't shown).
                Credit a player here, or just write a name. Major changes stand out on the page.
            </p>
            <form method="POST" action="{{ route('panel.changelog.builds.entries.store', ['build' => $build->getKey()]) }}">
                @csrf
                @if ($editing !== null)
                    <input type="hidden" name="id" value="{{ $editing->getKey() }}">
                @endif
                <div class="tomyum-panel__row" style="flex-wrap: wrap">
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 140px">
                        <span class="input-container__label">Kind</span>
                        <select class="input-text" name="type">
                            @foreach ($types as $type => $label)
                                <option value="{{ $type }}" @if (($editing?->type ?? 'add') === $type) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                        <span class="input-container__label">Category</span>
                        <input class="input-text" type="text" name="category" maxlength="255" list="tomyum-changelog-categories" value="{{ $editing?->category }}" placeholder="e.g. Multiplayer">
                        <datalist id="tomyum-changelog-categories">
                            @foreach ($categories as $category)
                                <option value="{{ $category }}">
                            @endforeach
                        </datalist>
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Title</span>
                        <input class="input-text" type="text" name="title" maxlength="255" required value="{{ $editing?->title }}">
                    </label>
                </div>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Message (optional, markdown)</span>
                    <textarea class="input-text" name="message" style="min-height: 140px; font-family: monospace">{{ $editing?->message }}</textarea>
                </label>
                <div class="tomyum-panel__row" style="flex-wrap: wrap">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Link (optional)</span>
                        <input class="input-text" type="url" name="url" maxlength="255" value="{{ $editing?->url }}" placeholder="https://…">
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                        <span class="input-container__label">Credit a player (name or id)</span>
                        <input class="input-text" type="text" name="user" value="{{ $editing?->tomyumUser?->username }}">
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                        <span class="input-container__label">…or just a name</span>
                        <input class="input-text" type="text" name="author" maxlength="100" value="{{ $editing?->tomyum_author }}">
                    </label>
                </div>
                <div class="tomyum-panel__row">
                    <label class="tomyum-panel__check"><input type="checkbox" name="major" value="1" @if ($editing?->major) checked @endif> Major</label>
                    <label class="tomyum-panel__check"><input type="checkbox" name="private" value="1" @if ($editing?->private) checked @endif> Hidden</label>
                </div>
                <div>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">{{ $editing === null ? 'Add change' : 'Save' }}</button>
                    @if ($editing !== null)
                        <a href="{{ route('panel.changelog.builds.show', ['build' => $build->getKey()]) }}" style="margin-left: 10px">Add a new one instead</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-list"></i>Changes</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Kind</th><th>Category</th><th>Title</th><th>By</th><th></th></tr>
                    @foreach ($entries as $entry)
                        <tr>
                            <td>{{ $types[$entry->type] ?? $entry->type }}</td>
                            <td>{{ $entry->category }}</td>
                            <td>
                                {{ $entry->title }}
                                @if ($entry->major) <span class="tomyum-panel__pill tomyum-panel__pill--pink">major</span> @endif
                                @if ($entry->private) <span class="tomyum-panel__pill tomyum-panel__pill--warn">hidden</span> @endif
                            </td>
                            <td>{{ $credit($entry) }}</td>
                            <td class="tomyum-panel__nowrap">
                                <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.changelog.builds.show', ['build' => $build->getKey(), 'edit' => $entry->getKey()]) }}">Edit</a>
                                <form method="POST" action="{{ route('panel.changelog.builds.entries.destroy', ['build' => $build->getKey(), 'entry' => $entry->getKey()]) }}" style="display: inline"
                                    onsubmit="return confirm('Remove this change?')">
                                    @csrf
                                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
@endsection
