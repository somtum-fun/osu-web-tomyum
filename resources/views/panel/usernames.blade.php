{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: blacklisted usernames (admins and developers). --}}
@extends('master', ['titlePrepend' => 'Blacklisted usernames'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.index'), 'title' => 'Staff panel'],
        ['title' => 'Blacklisted usernames'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-user-slash"></i>Blacklist a username</h2>
            <p class="tomyum-panel__hint">
                Nobody can sign up with a blacklisted name (on the website, in osu!lazer or in osu!(stable)), or rename to it.
                Case doesn't matter, and <code>%</code> means "anything": <code>%admin%</code> blocks every name with "admin" in it.
                Players who already have the name keep it.
            </p>
            <form method="POST" action="{{ route('panel.usernames.store') }}" class="tomyum-panel__row">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Username or pattern</span>
                    <input class="input-text" type="text" name="username" maxlength="255" required>
                </label>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Blacklist</button></div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-search"></i>Check a name</h2>
            <form method="GET" action="{{ route('panel.usernames.index') }}" class="tomyum-panel__row">
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Username</span>
                    <input class="input-text" type="text" name="check" value="{{ $check }}" maxlength="255">
                </label>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Check</button></div>
            </form>
            @if ($checked !== null)
                <p>
                    @if ($checked)
                        <span class="tomyum-panel__pill tomyum-panel__pill--good">allowed</span> "{{ $check }}" isn't blacklisted.
                    @else
                        <span class="tomyum-panel__pill tomyum-panel__pill--bad">blacklisted</span> "{{ $check }}" can't be used.
                    @endif
                </p>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-ban"></i>Blacklisted usernames</h2>
            @if (count($names) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Username or pattern</th><th>Players who have it now</th><th></th></tr>
                    @foreach ($names as $name)
                        <tr>
                            <td><code>{{ $name->disallow_username }}</code></td>
                            <td>
                                @forelse ($holders[$name->disallow_id] ?? [] as $holder)
                                    <a href="{{ route('users.show', ['user' => $holder->getKey()]) }}">{{ $holder->username }}</a>@if (!$loop->last), @endif
                                @empty
                                    <span class="tomyum-panel__hint">nobody</span>
                                @endforelse
                            </td>
                            <td style="text-align: right">
                                <form method="POST" action="{{ route('panel.usernames.destroy', ['name' => $name->disallow_id]) }}"
                                    onsubmit="return confirm('Allow this username again?')">
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
