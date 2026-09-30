{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Badges'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Badges'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-search"></i>Player</h2>
            <form method="GET" action="{{ route('panel.admin.badges.index') }}" class="tomyum-panel__row">
                <label class="tomyum-panel__field">
                    Name or id
                    <input type="text" name="user" value="{{ $query }}" required>
                </label>
                <div><button class="tomyum-panel__button" type="submit">Show</button></div>
            </form>
            @if (count($holders) > 0)
                <p class="tomyum-panel__hint" style="margin-top: 12px">
                    With badges:
                    @foreach ($holders as $holder)
                        <a href="{{ route('panel.admin.badges.index', ['user' => $holder['name']]) }}">{{ $holder['name'] }}</a> ({{ $holder['count'] }})@if (!$loop->last), @endif
                    @endforeach
                </p>
            @endif
        </div>

        @if ($query !== '' && $user === null)
            <div class="tomyum-panel__card"><p>There's no player called "{{ $query }}".</p></div>
        @elseif ($user !== null)
            <div class="tomyum-panel__card">
                <h2><a href="{{ route('users.show', ['user' => $user->getKey()]) }}">{{ $user->username }}</a>'s badges</h2>
                @if (count($badges) === 0)
                    <p class="tomyum-panel__empty">None yet.</p>
                @else
                    <table class="tomyum-panel__table">
                        <tr><th>Badge</th><th>Tooltip</th><th>Link</th><th>Given</th><th></th></tr>
                        @foreach ($badges as $badge)
                            <tr>
                                <td><img src="{{ $badge->imageUrl() }}" alt="" style="height: 40px"></td>
                                <td>{{ $badge->description }}</td>
                                <td>@if (present($badge->url))<a href="{{ $badge->url }}" rel="nofollow noopener" target="_blank">link</a>@else – @endif</td>
                                <td>{{ $badge->awarded?->toDateString() ?? '–' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('panel.admin.badges.destroy', ['user' => $user->getKey()]) }}"
                                        onsubmit="return confirm('Take this badge away?')">
                                        @csrf
                                        <input type="hidden" name="image" value="{{ $badge->image }}">
                                        <button class="tomyum-panel__button tomyum-panel__button--danger" type="submit">Take away</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>

            <div class="tomyum-panel__card">
                <h2><i class="fas fa-plus"></i>Give a badge</h2>
                <form method="POST" action="{{ route('panel.admin.badges.store', ['user' => $user->getKey()]) }}" class="tomyum-panel">
                    @csrf
                    <div class="tomyum-panel__row">
                        <label class="tomyum-panel__field">
                            Image: a link to any image (fitted to 86×40), or an osu! badge's file name (like contributor.png)
                            <input type="text" name="image" required placeholder="https://… or contributor.png">
                        </label>
                    </div>
                    <div class="tomyum-panel__row">
                        <label class="tomyum-panel__field">
                            Tooltip
                            <input type="text" name="description" required maxlength="255" placeholder="Server tester">
                        </label>
                        <label class="tomyum-panel__field">
                            Link when clicked (optional)
                            <input type="text" name="url" maxlength="255" placeholder="https://…">
                        </label>
                    </div>
                    <div><button class="tomyum-panel__button" type="submit">Give it</button></div>
                </form>
            </div>
        @endif
    </div>
@endsection
