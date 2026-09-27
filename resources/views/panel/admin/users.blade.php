{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Accounts'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Accounts'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <form method="GET" action="{{ route('panel.admin.users.index') }}" class="tomyum-panel__row">
                <label class="tomyum-panel__field">
                    Username, user id or email
                    <input type="text" name="q" value="{{ $query }}" autofocus>
                </label>
                <button class="tomyum-panel__button" type="submit">Find</button>
            </form>
        </div>
        @if ($query !== '')
            <div class="tomyum-panel__card">
                @if (count($users) === 0)
                    <p class="tomyum-panel__hint">Nobody matches "{{ $query }}".</p>
                @else
                    <table class="tomyum-panel__table">
                        <tr><th>Player</th><th>Id</th><th>Country</th><th>Joined</th></tr>
                        @foreach ($users as $user)
                            <tr>
                                <td><a href="{{ route('panel.admin.users.show', ['user' => $user->getKey()]) }}">{{ $user->username }}</a></td>
                                <td>{{ $user->getKey() }}</td>
                                <td>{{ $user->country_acronym }}</td>
                                <td>{{ $user->user_regdate?->toFormattedDateString() }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @endif
    </div>
@endsection
