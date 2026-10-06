{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $links = [
        [
            'title' => osu_trans('layout.header.tournaments.index'),
            'url' => route('tournaments.index'),
        ],
        [
            'title' => $tournament->name,
            'url' => route('tournaments.show', $tournament),
        ],
    ];
@endphp

@extends('master', ['titlePrepend' => $tournament->name])

@section('content')
    @include('layout._page_header_v4', ['params' => [
        'links' => $links,
        'linksBreadcrumb' => true,
        'theme' => 'tournaments',
    ]])
    <style>
        :root { {{ css_var_2x('--tournament-header-banner', $tournament->header_banner) }} }
    </style>

    <div class="osu-page osu-page--info-bar">
        <div class="grid-items">
            <div class="counter-box counter-box--info">
                <div class="counter-box__title">
                    {{ osu_trans('tournament.show.period.start') }}
                </div>
                <div class="counter-box__count">
                    {{ i18n_date($tournament->start_date) }}
                </div>
            </div>
            <div class="counter-box counter-box--info">
                <div class="counter-box__title">
                    {{ osu_trans('tournament.show.period.end') }}
                </div>
                <div class="counter-box__count">
                    {{ i18n_date($tournament->end_date) }}
                </div>
            </div>
        </div>
    </div>

    <div class="osu-page">
        <div class="tournament">
            {{-- TomYum: no empty space where a tournament has no header image yet. --}}
            @if (present($tournament->header_banner))
                <div class='tournament__banner'></div>
            @endif

            <div class="tournament__page">
                @if (count($links = $tournament->pageLinks()) > 0)
                    <div class="tournament__links">
                        @foreach ($links as $link)
                            <a
                                href="{{ $link['url'] }}"
                                class="btn-osu-big btn-osu-big--tournament-info"
                            >{{ $link['title'] }}</a>
                        @endforeach
                    </div>
                @endif

                <div class="tournament__description">
                    {{-- TomYum: drafts are only for staff; the description always shows, the state under it. --}}
                    @if ($tournament->is_hidden)
                        <p class="tournament__notice">{{ osu_trans('tournament.show.hidden') }}</p>
                    @endif

                    @if (present($tournament->description))
                        {!! markdown($tournament->description) !!}
                    @endif

                    <div class="tournament__description-md-extra">
                        @if ($tournament->signup_open->isFuture())
                            {{ osu_trans('tournament.show.state.before_registration') }}
                            {{ osu_trans('tournament.show.registration_opens', ['date' => i18n_date($tournament->signup_open)]) }}.
                        @elseif ($tournament->isRegistrationOpen())
                            {{ osu_trans('tournament.show.registration_ends', ['date' => i18n_date($tournament->signup_close)]) }}.
                        @elseif ($tournament->start_date->isFuture())
                            {{ osu_trans('tournament.show.state.registration_closed') }}
                        @elseif ($tournament->isTournamentRunning())
                            {{ osu_trans('tournament.show.state.running') }}
                        @else
                            {{ osu_trans('tournament.show.state.ended') }}
                        @endif
                    </div>

                    {{-- TomYum: who can register; ticked or crossed for the player looking (banner-only ones have no registration). --}}
                    @if (!$tournament->is_banner_only)
                    <h3>{{ osu_trans('tournament.show.requirements') }}</h3>
                    <ul class="tournament__requirements">
                        @foreach ($requirements as $key => $text)
                            @php
                                $checked = Auth::user() !== null;
                                $met = !isset($failures[$key]);
                            @endphp
                            <li class="{{ class_with_modifiers('tournament__requirement', ['met' => $checked && $met, 'unmet' => $checked && !$met]) }}">
                                @if ($checked)
                                    <i class="fas fa-fw {{ $met ? 'fa-check' : 'fa-times' }}"></i>
                                @else
                                    <i class="fas fa-fw fa-angle-right"></i>
                                @endif
                                {{ $text }}
                            </li>
                        @endforeach
                    </ul>
                    @endif
                </div>
            </div>

            @if(!$tournament->is_banner_only && $tournament->isRegistrationOpen()) {{-- TomYum: not banner-only ones --}}
                <div class='tournament__countdown-timer'>
                    <div
                        class='js-react'
                        data-react='countdownTimer'
                        data-deadline='{{json_time($tournament->signup_close)}}'
                    ></div>
                </div>

                <div class="tournament__body">
                    @if (!Auth::user())
                        <div>{!!
                            osu_trans('tournament.show.login_to_register', [
                                'login' => '<a href="#" class="js-user-link" title="'.osu_trans("users.anonymous.login_link").'">'.osu_trans("users.anonymous.login_text").'</a>'
                            ])
                        !!}</div>
                    @elseif ($isSignedUp)
                        <div>{!!osu_trans('tournament.show.entered')!!}</div>
                        <a
                            href="{{route("tournaments.unregister", $tournament) }}"
                            class="btn-osu-big btn-osu-big--tournament-register"
                            data-method="post"
                            data-remote="1"
                        >
                            {{osu_trans('tournament.show.button.cancel')}}
                        </a>
                    @elseif (count($failures) === 0)
                        <div>{{osu_trans('tournament.show.not_yet_entered')}}</div>
                        <a
                            href="{{ route("tournaments.register", $tournament) }}"
                            class="btn-osu-big btn-osu-big--tournament-register"
                            data-method="post"
                            data-remote="1"
                        >
                            {{osu_trans('tournament.show.button.register')}}
                        </a>
                    @else
                        {{-- TomYum: every reason, in plain words. --}}
                        <div>{{ osu_trans('tournament.show.cannot_register') }}</div>
                        <ul class="tournament__failures">
                            @foreach ($failures as $failure)
                                <li>{{ $failure }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            {{-- TomYum: the registered players, for everyone. --}}
            @if (!$tournament->is_banner_only)
            <div class="tournament__page tournament__registrants">
                <h3>
                    {{ osu_trans('tournament.show.registrants') }}
                    <small>
                        @if ($tournament->max_registrations === null)
                            {{ osu_trans('tournament.show.registered_count', ['count' => i18n_number_format(count($registrations))]) }}
                        @else
                            {{ osu_trans('tournament.show.registered_count_max', ['count' => i18n_number_format(count($registrations)), 'max' => i18n_number_format($tournament->max_registrations)]) }}
                        @endif
                    </small>
                </h3>
                @if (count($registrations) === 0)
                    <p>{{ osu_trans('tournament.show.none_registered') }}</p>
                @else
                    <table class="ranking-page-table">
                        <tbody>
                            @foreach ($registrations->values() as $index => $registration)
                                <tr class="ranking-page-table__row">
                                    <td class="ranking-page-table__column">
                                        {{ i18n_number_format($index + 1) }}
                                    </td>
                                    <td class="ranking-page-table__column ranking-page-table__column--main">
                                        @include('rankings._main_column', ['object' => $registration->user, 'params' => ['mode' => $mode, 'variant' => $tournament->play_mode_variant]])
                                    </td>
                                    <td class="ranking-page-table__column ranking-page-table__column--dimmed">
                                        @if (($ranks[$registration->user_id] ?? null) !== null)
                                            #{{ i18n_number_format($ranks[$registration->user_id]) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @endif
        </div>
    </div>
@stop
