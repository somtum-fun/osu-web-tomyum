{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: one tournament (Panel\TournamentsController): its details and rules, registrations and log. --}}
@extends('master', ['titlePrepend' => $tournament->name])

@section('content')
    @php
        $panelPrefix = App\Http\Controllers\Panel\Controller::sharedPanelPrefix();
        $key = ['tournament' => $tournament->getKey()];
        $dateValue = fn ($date) => $date?->copy()->setTimezone('UTC')->format('Y-m-d\TH:i');
        $flagged = $registrations->filter(fn ($registration) => count($registration->ineligible_reasons ?? []) > 0)->count();
    @endphp
    @include('panel._header', ['crumbs' => [
        ['url' => route($panelPrefix.'index'), 'title' => $panelPrefix === 'panel.tc.' ? 'TC panel' : 'Admin panel'],
        ['url' => route($panelPrefix.'tournaments.index'), 'title' => 'Tournaments'],
        ['title' => $tournament->name],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2>
                    <i class="fas fa-trophy"></i>{{ $tournament->name }}
                    @if ($tournament->is_banner_only)
                        <span class="tomyum-panel__pill tomyum-panel__pill--pink">banner only</span>
                    @elseif ($tournament->is_hidden)
                        <span class="tomyum-panel__pill">hidden</span>
                    @else
                        <span class="tomyum-panel__pill tomyum-panel__pill--good">shown</span>
                    @endif
                </h2>
                <div class="tomyum-panel__buttons">
                    <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('tournaments.show', $tournament) }}">
                        <i class="fas fa-external-link-alt"></i>Its page
                    </a>
                    @if ($canDelete)
                        <form method="POST" action="{{ route($panelPrefix.'tournaments.destroy', $key) }}"
                            onsubmit="return confirm('Remove this tournament{{ $registrations->isEmpty() ? '' : ' and all its registrations' }}? This can\'t be undone.')">
                            @csrf
                            <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--danger" type="submit"><i class="fas fa-trash"></i>Remove</button>
                        </form>
                    @endif
                </div>
            </div>
            @if (!$canDelete)
                <p class="tomyum-panel__hint">Players have registered, so only an admin can remove it. You can hide it instead.</p>
            @endif
            @if ($hasBanner)
                <p class="tomyum-panel__hint">
                    It has profile banners: they're managed on the <a href="{{ route($panelPrefix.'tournament-banners.index') }}#banner-{{ $tournament->getKey() }}">Banners page</a>.
                </p>
            @endif

            <form method="POST" action="{{ route($panelPrefix.'tournaments.update', $key) }}" class="panel-banners__form">
                @csrf
                <label class="input-container panel-banners__wide">
                    <span class="input-container__label">Name</span>
                    <input class="input-text" type="text" name="name" required maxlength="255" value="{{ $tournament->name }}">
                </label>
                <label class="tomyum-panel__check">
                    <input type="checkbox" name="is_hidden" value="1" @checked($tournament->is_hidden)> Hidden (only staff can see it)
                </label>
                <label class="tomyum-panel__check">
                    <input type="checkbox" name="is_banner_only" value="1" @checked($tournament->is_banner_only)> Banner only (just for profile banners: not listed, no registration)
                </label>
                <label class="input-container panel-banners__wide">
                    <span class="input-container__label">Description (markdown: **bold**, [a link](https://…), a blank line between paragraphs; the requirements below are listed under it by themselves)</span>
                    <textarea class="input-text" name="description" rows="10">{{ $tournament->description }}</textarea>
                </label>
                <label class="input-container">
                    <span class="input-container__label">Mode</span>
                    <select class="input-text" name="mode">
                        @foreach ($modes as $value => $label)
                            <option value="{{ $value }}" @selected((int) $tournament->play_mode === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="input-container">
                    <span class="input-container__label">Key count (osu!mania only; the rank is checked on that board)</span>
                    <select class="input-text" name="variant">
                        <option value="">All</option>
                        @foreach (App\Models\Beatmap::VARIANTS['mania'] as $variant)
                            <option value="{{ $variant }}" @selected($tournament->play_mode_variant === $variant)>{{ strtoupper($variant) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="input-container panel-banners__wide">
                    <span class="input-container__label">Information page (optional: a forum post, a spreadsheet, a website)</span>
                    <input class="input-text" type="text" name="info_url" maxlength="255" value="{{ $tournament->info_url }}" placeholder="https://…">
                </label>

                <p class="tomyum-panel__section-title panel-banners__wide">Dates (UTC)</p>
                @foreach (['signup_open' => 'Registration opens', 'signup_close' => 'Registration closes (the deadline)', 'start_date' => 'Starts', 'end_date' => 'Ends'] as $column => $label)
                    <label class="input-container">
                        <span class="input-container__label">{{ $label }}</span>
                        <input class="input-text" type="datetime-local" name="{{ $column }}" required value="{{ $dateValue($tournament->$column) }}">
                    </label>
                @endforeach

                <p class="tomyum-panel__section-title panel-banners__wide">Who can register (empty: no rule). Restricted players and players with a tournament ban never can.</p>
                <label class="input-container">
                    <span class="input-container__label">Best rank allowed (like 1)</span>
                    <input class="input-text" type="number" name="rank_min" min="1" value="{{ $tournament->rank_min }}" placeholder="any">
                </label>
                <label class="input-container">
                    <span class="input-container__label">Worst rank allowed (like 10000)</span>
                    <input class="input-text" type="number" name="rank_max" min="1" value="{{ $tournament->rank_max }}" placeholder="any">
                </label>
                <label class="input-container panel-banners__wide">
                    <span class="input-container__label">Countries (two-letter codes, like TH, JP; their profile's country)</span>
                    <input class="input-text" type="text" name="countries" maxlength="1000" value="{{ implode(', ', $tournament->countries ?? []) }}" placeholder="everyone">
                </label>
                <label class="input-container">
                    <span class="input-container__label">No restriction in the last … days</span>
                    <input class="input-text" type="number" name="restriction_days" min="1" max="3650" value="{{ $tournament->restriction_days }}" placeholder="no rule">
                </label>
                <label class="input-container">
                    <span class="input-container__label">No silence in the last … days</span>
                    <input class="input-text" type="number" name="silence_days" min="1" max="3650" value="{{ $tournament->silence_days }}" placeholder="no rule">
                </label>
                <label class="input-container">
                    <span class="input-container__label">Account at least … days old</span>
                    <input class="input-text" type="number" name="account_age_days" min="1" max="3650" value="{{ $tournament->account_age_days }}" placeholder="no rule">
                </label>
                <label class="input-container">
                    <span class="input-container__label">Player cap</span>
                    <input class="input-text" type="number" name="max_registrations" min="1" value="{{ $tournament->max_registrations }}" placeholder="no cap">
                </label>
                <label class="tomyum-panel__check panel-banners__wide">
                    <input type="checkbox" name="requires_discord" value="1" @checked($tournament->requires_discord)> Needs a linked Discord account
                </label>

                <p class="tomyum-panel__section-title panel-banners__wide">Header image</p>
                <label class="input-container panel-banners__wide">
                    <span class="input-container__label">Its address (or upload one below)</span>
                    <input class="input-text" type="text" name="header_banner" maxlength="255" value="{{ $tournament->header_banner }}" placeholder="https://…">
                </label>

                <div class="panel-banners__wide"><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button></div>
            </form>

            @if (present($tournament->header_banner))
                <img class="panel-banners__image" src="{{ $tournament->header_banner }}" alt="">
            @endif
            <form method="POST" action="{{ route($panelPrefix.'tournaments.header', $key) }}" enctype="multipart/form-data" class="tomyum-panel__row">
                @csrf
                <label class="tomyum-panel__field">
                    Upload a header image (wide, about 2400×1000; it's shown at half that, and is cropped to fit)
                    <input type="file" name="header" accept="image/*" required>
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-upload"></i>Upload</button>
            </form>
        </div>

        <div class="tomyum-panel__card" id="registrations">
            <div class="tomyum-panel__card-head">
                <h2>
                    <i class="fas fa-users"></i>Registered players
                    <span class="tomyum-panel__hint">{{ i18n_number_format(count($registrations)) }}{{ $tournament->max_registrations === null ? '' : ' / '.i18n_number_format($tournament->max_registrations) }}</span>
                    @if ($flagged > 0)
                        <span class="tomyum-panel__pill tomyum-panel__pill--bad">{{ $flagged }} no longer qualify</span>
                    @endif
                </h2>
                <div class="tomyum-panel__buttons">
                    <form method="POST" action="{{ route($panelPrefix.'tournaments.recheck', $key) }}">
                        @csrf
                        <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" type="submit"><i class="fas fa-redo"></i>Check again</button>
                    </form>
                    <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route($panelPrefix.'tournaments.export', $key) }}">
                        <i class="fas fa-file-csv"></i>Export (CSV)
                    </a>
                </div>
            </div>
            <p class="tomyum-panel__hint">
                When registration closes, everyone registered is checked against the requirements again, and whoever no longer
                qualifies (restricted since, rank changed, …) is marked here for you to decide.
                @if ($tournament->rechecked_at !== null)
                    Last checked {!! timeago($tournament->rechecked_at) !!}.
                @endif
            </p>
            @if ($registrations->isEmpty())
                <p class="tomyum-panel__empty">Nobody has registered yet.</p>
            @else
                <div class="tomyum-panel__scroll">
                    <table class="tomyum-panel__table">
                        <tr>
                            <th>#</th>
                            <th>Player</th>
                            <th>Country</th>
                            <th>Rank</th>
                            <th>Registered</th>
                            <th>Still qualifies</th>
                            <th></th>
                        </tr>
                        @foreach ($registrations->values() as $index => $registration)
                            @php
                                $user = $registration->user;
                                $rank = ($stats[$registration->user_id] ?? null)?->globalRank();
                                $reasons = $registration->ineligible_reasons ?? [];
                            @endphp
                            <tr>
                                <td class="tomyum-panel__muted">{{ $index + 1 }}</td>
                                <td class="tomyum-panel__nowrap">
                                    @if ($user === null)
                                        #{{ $registration->user_id }} <span class="tomyum-panel__muted">(deleted)</span>
                                    @else
                                        <a href="{{ route('users.show', ['user' => $user->getKey()]) }}">{{ $user->username }}</a>
                                        @if ($user->isRestricted())
                                            <span class="tomyum-panel__pill tomyum-panel__pill--bad">restricted</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="tomyum-panel__nowrap">
                                    {{ $registration->country_acronym ?? '?' }}
                                    @if ($user !== null && $registration->country_acronym !== null && $user->country_acronym !== $registration->country_acronym)
                                        <span class="tomyum-panel__pill tomyum-panel__pill--warn" title="Their profile's country now">now {{ $user->country_acronym }}</span>
                                    @endif
                                </td>
                                <td class="tomyum-panel__nowrap">{{ $rank === null ? '-' : '#'.i18n_number_format($rank) }}</td>
                                <td class="tomyum-panel__nowrap tomyum-panel__muted">{!! timeago($registration->created_at) !!}</td>
                                <td>
                                    @if ($registration->checked_at === null)
                                        <span class="tomyum-panel__muted">not checked yet</span>
                                    @elseif (count($reasons) === 0)
                                        <span class="tomyum-panel__pill tomyum-panel__pill--good">yes</span>
                                    @else
                                        <span class="tomyum-panel__pill tomyum-panel__pill--bad">no</span>
                                        <span class="tomyum-panel__muted">{{ implode(' ', $reasons) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route($panelPrefix.'tournaments.registrations.destroy', [...$key, 'user' => $registration->user_id]) }}"
                                        class="tomyum-panel__row"
                                        onsubmit="return confirm('Remove this player\'s registration?')">
                                        @csrf
                                        <input class="input-text" type="text" name="reason" maxlength="255" placeholder="Why (optional)" aria-label="Why">
                                        <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--danger" type="submit"><i class="fas fa-user-minus"></i>Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-history"></i>Staff log</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">Nothing yet.</p>
            @else
                @include('panel._staff_log')
            @endif
        </div>
    </div>
@endsection
