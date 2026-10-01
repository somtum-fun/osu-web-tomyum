{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'News'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'News'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-pen"></i>{{ $editing === null ? 'Write a post' : 'Edit "'.$editing->title().'"' }}</h2>
            <p class="tomyum-panel__hint">
                Shown on the website's news page, the dashboard and in lazer. Written in the osu! wiki's markdown:
                **bold**, *italic*, [links](https://osu.sundei.eu), ![images](https://…/image.jpg) (the first image is
                the post's cover), ## headings, - lists. A whole osu-wiki news file works too: its header (between the
                --- lines: title, date, series) fills whatever is left empty here, and a last line like "—Name" signs it.
            </p>
            <form method="POST" action="{{ route('panel.admin.news.store') }}" class="tomyum-panel">
                @csrf
                @if ($editing !== null)
                    <input type="hidden" name="id" value="{{ $editing->getKey() }}">
                @endif
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Title</span>
                        <input class="input-text" type="text" name="title" maxlength="200" value="{{ $editing?->title() }}" placeholder="or from the text's header">
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                        <span class="input-container__label">Date (UTC; empty: now)</span>
                        <input class="input-text" type="text" name="date" placeholder="2026-09-29 18:00" value="{{ $editing?->published_at?->format('Y-m-d H:i') ?? ($editing ? $editing->page['header']['date'] ?? '' : '') }}">
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
                        <span class="input-container__label">Author (empty: you)</span>
                        <input class="input-text" type="text" name="author" value="{{ $editing?->page['header']['author'] ?? '' }}">
                    </label>
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 200px">
                        <span class="input-container__label">Series</span>
                        <select class="input-text" name="series">
                            @foreach ($series as $name)
                                <option value="{{ $name }}" @if (($editing?->series() ?? 'miscellaneous') === $name) selected @endif>{{ str_replace('_', ' ', $name) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Text</span>
                    <textarea class="input-text" name="body" required style="min-height: 320px; font-family: monospace">{{ $editing?->page['tomyum_markdown'] ?? '' }}</textarea>
                </label>
                <div class="tomyum-panel__row">
                    <label><input type="checkbox" name="draft" value="1" @if ($editing !== null && $editing->published_at === null) checked @endif> Draft (not shown yet)</label>
                </div>
                <div>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">{{ $editing === null ? 'Post' : 'Save' }}</button>
                    @if ($editing !== null)
                        <a href="{{ route('panel.admin.news.index') }}" style="margin-left: 10px">Write a new one instead</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-newspaper"></i>Posts</h2>
            @if (count($posts) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Title</th><th>Date</th><th></th></tr>
                    @foreach ($posts as $post)
                        <tr>
                            <td>
                                @if ($post->published_at !== null)
                                    <a href="{{ $post->url() }}">{{ $post->title() }}</a>
                                @else
                                    {{ $post->title() }} <span class="tomyum-panel__hint">(draft)</span>
                                @endif
                            </td>
                            <td>{{ $post->published_at?->toDateString() ?? '–' }}</td>
                            <td style="white-space: nowrap">
                                <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.admin.news.index', ['edit' => $post->getKey()]) }}">Edit</a>
                                <form method="POST" action="{{ route('panel.admin.news.destroy', ['post' => $post->getKey()]) }}" style="display: inline"
                                    onsubmit="return confirm('Remove this post for good?')">
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
