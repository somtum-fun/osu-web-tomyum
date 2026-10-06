{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the tournaments (Panel\TournamentsController), shared by the admin and TC panels. --}}
@extends('master', ['titlePrepend' => 'Tournaments'])

@section('content')
    @php
        $panelPrefix = App\Http\Controllers\Panel\Controller::sharedPanelPrefix();
        $modes = App\Http\Controllers\Panel\TournamentsController::MODES;
    @endphp
    @include('panel._header', ['crumbs' => [
        ['url' => route($panelPrefix.'index'), 'title' => $panelPrefix === 'panel.tc.' ? 'TC panel' : 'Admin panel'],
        ['title' => 'Tournaments'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Make a tournament</h2>
            <p class="tomyum-panel__hint">
                Tournaments show on <a href="{{ route('tournaments.index') }}">Community → Tournaments</a>, where logged-in players
                register (solo). A new one is hidden until you're ready: give it a name, then fill in its dates, rules and header image.
            </p>
            <form method="POST" action="{{ route($panelPrefix.'tournaments.store') }}" class="panel-banners__form">
                @csrf
                <label class="input-container">
                    <span class="input-container__label">Name</span>
                    <input class="input-text" type="text" name="name" required maxlength="255" placeholder="production World Cup 2026">
                </label>
                <label class="input-container input-container--select">
                    <span class="input-container__label">Mode</span>
                    <select class="input-text" name="mode">
                        @foreach ($modes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-plus"></i>Make it</button></div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-trophy"></i>Tournaments</h2>
            @if ($tournaments->isEmpty())
                <p class="tomyum-panel__empty">No tournaments yet.</p>
            @else
                <div class="tomyum-panel__scroll">
                    <table class="tomyum-panel__table">
                        <tr>
                            <th>Name</th>
                            <th>Mode</th>
                            <th>Registration</th>
                            <th>Runs</th>
                            <th>Players</th>
                            <th></th>
                        </tr>
                        @foreach ($tournaments as $tournament)
                            <tr>
                                <td>
                                    <a href="{{ route($panelPrefix.'tournaments.show', ['tournament' => $tournament->getKey()]) }}">{{ $tournament->name }}</a>
                                </td>
                                <td class="tomyum-panel__nowrap">{{ $modes[$tournament->play_mode] ?? '?' }}{{ $tournament->play_mode_variant === null ? '' : " {$tournament->play_mode_variant}" }}</td>
                                <td class="tomyum-panel__muted"><span class="tomyum-panel__nowrap">{{ i18n_date($tournament->signup_open, IntlDateFormatter::MEDIUM) }} –</span> <span class="tomyum-panel__nowrap">{{ i18n_date($tournament->signup_close, IntlDateFormatter::MEDIUM) }}</span></td>
                                <td class="tomyum-panel__muted"><span class="tomyum-panel__nowrap">{{ i18n_date($tournament->start_date, IntlDateFormatter::MEDIUM) }} –</span> <span class="tomyum-panel__nowrap">{{ i18n_date($tournament->end_date, IntlDateFormatter::MEDIUM) }}</span></td>
                                <td>
                                    {{ i18n_number_format($tournament->registrations_count) }}{{ $tournament->max_registrations === null ? '' : ' / '.i18n_number_format($tournament->max_registrations) }}
                                </td>
                                <td class="tomyum-panel__nowrap">
                                    @if ($tournament->is_banner_only)
                                        <span class="tomyum-panel__pill tomyum-panel__pill--pink">banner only</span>
                                    @elseif ($tournament->is_hidden)
                                        <span class="tomyum-panel__pill">hidden</span>
                                    @elseif ($tournament->isRegistrationOpen())
                                        <span class="tomyum-panel__pill tomyum-panel__pill--good">registration open</span>
                                    @elseif ($tournament->end_date->isPast())
                                        <span class="tomyum-panel__pill">over</span>
                                    @else
                                        <span class="tomyum-panel__pill tomyum-panel__pill--info">shown</span>
                                    @endif
                                    @if (isset($banners[$tournament->getKey()]))
                                        <span class="tomyum-panel__pill tomyum-panel__pill--pink" title="It has profile banners (Banners page)"><i class="fas fa-image"></i></span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>

        <div class="tomyum-panel__card" id="bans">
            <h2><i class="fas fa-ban"></i>Tournament bans</h2>
            <p class="tomyum-panel__hint">
                A player with a tournament ban can't register for any tournament (it shows on their profile's account standing,
                like osu!'s). Leave the length empty for a ban that lasts until it's lifted.
            </p>
            <form method="POST" action="{{ route($panelPrefix.'tournaments.bans.store') }}" class="panel-banners__form">
                @csrf
                <label class="input-container">
                    <span class="input-container__label">Player (name or id)</span>
                    <input class="input-text" type="text" name="user" required maxlength="255">
                </label>
                <label class="input-container">
                    <span class="input-container__label">Length in days (empty: until lifted)</span>
                    <input class="input-text" type="number" name="days" min="1" max="3650" placeholder="until lifted">
                </label>
                <label class="input-container panel-banners__wide">
                    <span class="input-container__label">Reason</span>
                    <input class="input-text" type="text" name="reason" required maxlength="2000">
                </label>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-ban"></i>Ban from tournaments</button></div>
            </form>

            @if ($bans->isEmpty())
                <p class="tomyum-panel__empty">Nobody has a tournament ban right now.</p>
            @else
                <div class="tomyum-panel__scroll">
                    <table class="tomyum-panel__table">
                        <tr>
                            <th>Player</th>
                            <th>Since</th>
                            <th>Until</th>
                            <th>By</th>
                            <th>Reason</th>
                            <th></th>
                        </tr>
                        @foreach ($bans as $ban)
                            <tr>
                                <td class="tomyum-panel__nowrap">
                                    @if ($ban->user === null)
                                        #{{ $ban->user_id }}
                                    @else
                                        <a href="{{ route('users.show', ['user' => $ban->user_id]) }}">{{ $ban->user->username }}</a>
                                    @endif
                                </td>
                                <td class="tomyum-panel__nowrap tomyum-panel__muted">{!! timeago($ban->timestamp) !!}</td>
                                <td class="tomyum-panel__nowrap">{{ $ban->permanent ? 'it\'s lifted' : i18n_date($ban->endTime(), IntlDateFormatter::MEDIUM) }}</td>
                                <td class="tomyum-panel__nowrap">{{ $ban->actor?->username ?? '-' }}</td>
                                <td>{{ $ban->reason }}</td>
                                <td>
                                    <form method="POST" action="{{ route($panelPrefix.'tournaments.bans.destroy', ['user' => $ban->user_id]) }}"
                                        class="panel-banners__inline panel-banners__inline--row"
                                        onsubmit="return confirm('Lift this tournament ban?')">
                                        @csrf
                                        <input class="input-text" type="text" name="reason" maxlength="1900" placeholder="Why (optional)" aria-label="Why">
                                        <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" type="submit"><i class="fas fa-undo"></i>Lift</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
