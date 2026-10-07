{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Staff panel'])

@section('content')
    @include('panel._header')
    @php
        // The cards, by section: [shown, route, icon, kicker, title, hint, badge]. Big cards are the
        // panels people work in; the tools below are rows.
        $sections = [
            'Panels' => [
                [$panels['bn'], 'panel.bn.index', 'fa-music', 'Beatmap nominators', 'BN panel', 'Import sets from bancho, upload custom sets and manage their ranked status.', null],
                [$panels['gmt'], 'panel.gmt.index', 'fa-gavel', 'Moderation', 'GMT panel', 'Restrictions, silences, reports, chat logs and score removal.', null],
                [$panels['tc'], 'panel.tc.index', 'fa-trophy', 'Tournament Committee', 'TC panel', 'Tournaments, registrations and live matches, banners and badges.', null],
                [$panels['admin'], 'panel.admin.index', 'fa-cog', 'Administration', 'Admin panel', 'The server at a glance, accounts, groups, branding and the site\'s content.', null],
            ],
            'Community' => [
                [$panels['tickets'], 'panel.tickets.index', 'fa-inbox', 'Support', 'Tickets', 'Mail to support@, accounts@, appeals, abuse@, privacy@, dmca@ and tournaments@.', $openTickets > 0 ? "{$openTickets} open" : null],
                [$panels['loved'], 'loved.index', 'fa-heart', 'Project Loved', 'Loved requests', 'Players\' requests, most supported first: love, reject or blacklist.', null],
                [$panels['packs'] ?? false, 'panel.packs.index', 'fa-box-open', 'Admins and the NAT', 'Beatmap packs', 'The packs: their sets, mode and download.', null],
                [$panels['dan'] ?? false, 'panel.dan.index', 'fa-graduation-cap', 'Admins and the NAT', 'osu!dan', 'The courses: maps, accuracy, mods and order, and the latest attempts.', null],
            ],
            'Tools' => [
                [$panels['bots'], 'panel.bots.index', 'fa-robot', 'Admins and developers', 'Bots', 'IRC bot accounts: owners, names, avatars and passwords.', null],
                [$panels['kudosu'], 'panel.kudosu.index', 'fa-medal', 'Admins and developers', 'Kudosu', 'Give or take kudosu, with a reason on the profile.', null],
                [$panels['changelog'] ?? false, 'panel.changelog.index', 'fa-code-branch', 'Admins and developers', 'Changelog', 'Versions of the clients, the server and the website, and what changed.', null],
            ],
        ];
        $sections = array_filter(array_map(fn ($cards) => array_values(array_filter($cards, fn ($card) => $card[0])), $sections));
    @endphp
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__welcome">
            <h1>Hi, {{ Auth::user()->username }}</h1>
            <p class="tomyum-panel__hint">What would you like to work on?</p>
        </div>

        @foreach ($sections as $section => $cards)
            <p class="tomyum-panel__section-title">{{ $section }}</p>
            <div class="tomyum-panel__links {{ $loop->first ? 'tomyum-panel__links--panels' : 'tomyum-panel__links--rows' }}">
                @foreach ($cards as [, $route, $icon, $kicker, $title, $hint, $badge])
                    <a class="tomyum-panel__link {{ $loop->parent->first ? 'tomyum-panel__link--big' : 'tomyum-panel__link--row' }}" href="{{ route($route) }}">
                        <span class="tomyum-panel__link-icon"><i class="fas {{ $icon }}"></i></span>
                        <span class="tomyum-panel__link-text">
                            <span class="tomyum-panel__kicker">{{ $kicker }}</span>
                            <h2>
                                {{ $title }}
                                @if ($badge !== null)
                                    <span class="tomyum-panel__badge tomyum-panel__badge--success">{{ $badge }}</span>
                                @endif
                            </h2>
                            <span class="tomyum-panel__hint">{{ $hint }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
@endsection
