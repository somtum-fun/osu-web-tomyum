{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Titles'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Titles'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-user-tag"></i>Give a title</h2>
            <p class="tomyum-panel__hint">
                A title shows under the player's name on their profile (like osu!'s "Community Contributor"). A player can have two:
                the second shows after the first, "First / Second". Pick "(no title)" to take one away.
            </p>
            @if (count($titles) === 0)
                <p class="tomyum-panel__hint">Make a title below first.</p>
            @else
                <form method="POST" action="{{ route('panel.admin.titles.assign') }}" class="tomyum-panel__row">
                    @csrf
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Player (name or id)</span>
                        <input class="input-text" type="text" name="user" required>
                    </label>
                    <label class="input-container input-container--select tomyum-panel__field">
                        <span class="input-container__label">Title</span>
                        <select class="input-text" name="title">
                            @foreach ($titles as $title)
                                <option value="{{ $title->getKey() }}">{{ $title->rank_title }}</option>
                            @endforeach
                            <option value="0">(no title)</option>
                        </select>
                    </label>
                    {{-- Two titles show as osu!'s do: "Former osu! Champion: 2023 / Mapping Architect". --}}
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 170px">
                        <span class="input-container__label">As their</span>
                        <select class="input-text" name="slot">
                            <option value="1">first title</option>
                            <option value="2">second title</option>
                        </select>
                    </label>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button></div>
                </form>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-id-badge"></i>Titles</h2>
            @if (count($titles) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                @php
                    // Titles rarely link anywhere: only show the column when one does.
                    $withLinks = collect($titles)->contains(fn ($title) => present($title->url));
                @endphp
                <table class="tomyum-panel__table">
                    <tr><th>Title</th>@if ($withLinks)<th>Link</th>@endif<th>Players</th><th></th></tr>
                    @foreach ($titles as $title)
                        <tr>
                            <td><span class="tomyum-panel__pill tomyum-panel__pill--pink">{{ $title->rank_title }}</span></td>
                            @if ($withLinks)
                                <td>@if (present($title->url))<a href="{{ $title->url }}" rel="nofollow noopener" target="_blank"><i class="fas fa-external-link-alt"></i> link</a>@endif</td>
                            @endif
                            <td>
                                @forelse ($holders[$title->getKey()] ?? [] as $holder)
                                    <a href="{{ route('users.show', ['user' => $holder->getKey()]) }}">{{ $holder->username }}</a>@if (!$loop->last), @endif
                                @empty
                                    <span class="tomyum-panel__hint">nobody</span>
                                @endforelse
                            </td>
                            <td style="text-align: right">
                                <form method="POST" action="{{ route('panel.admin.titles.destroy', ['title' => $title->getKey()]) }}"
                                    onsubmit="return confirm('Remove this title (and take it from everyone who has it)?')">
                                    @csrf
                                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Make a title</h2>
            <form method="POST" action="{{ route('panel.admin.titles.store') }}" class="tomyum-panel__row">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Title</span>
                    <input class="input-text" type="text" name="title" required maxlength="100" placeholder="Server Tester">
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Link when clicked (optional)</span>
                    <input class="input-text" type="text" name="url" maxlength="255" placeholder="https://…">
                </label>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Make it</button></div>
            </form>
        </div>
    </div>
@endsection
