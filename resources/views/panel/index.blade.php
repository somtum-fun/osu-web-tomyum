{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Staff panel'])

@section('content')
    @include('panel._header')
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__links">
            @if ($panels['bn'])
                <a class="tomyum-panel__link tomyum-panel__link--big" href="{{ route('panel.bn.index') }}">
                    <span class="tomyum-panel__link-icon"><i class="fas fa-music"></i></span>
                    <span class="tomyum-panel__kicker">Beatmap nominators</span>
                    <h2>BN panel</h2>
                    <p class="tomyum-panel__hint">Import beatmap sets from bancho, upload custom sets and manage their ranked status.</p>
                </a>
            @endif
            @if ($panels['gmt'])
                <a class="tomyum-panel__link tomyum-panel__link--big" href="{{ route('panel.gmt.index') }}">
                    <span class="tomyum-panel__link-icon"><i class="fas fa-gavel"></i></span>
                    <span class="tomyum-panel__kicker">Moderation</span>
                    <h2>GMT panel</h2>
                    <p class="tomyum-panel__hint">Restrictions, silences, reports, chat logs and score removal.</p>
                </a>
            @endif
            @if ($panels['tickets'])
                <a class="tomyum-panel__link tomyum-panel__link--big" href="{{ route('panel.tickets.index') }}">
                    <span class="tomyum-panel__link-icon"><i class="fas fa-inbox"></i></span>
                    <span class="tomyum-panel__kicker">Support</span>
                    <h2>Tickets @if ($openTickets > 0)<span class="tomyum-panel__badge tomyum-panel__badge--success">{{ $openTickets }} open</span>@endif</h2>
                    <p class="tomyum-panel__hint">Emails to support@, accounts@, appeal-review@, abuse@, privacy@, dmca@ and tournaments@: reply, take them on and close them.</p>
                </a>
            @endif
            @if ($panels['bots'])
                <a class="tomyum-panel__link tomyum-panel__link--big" href="{{ route('panel.bots.index') }}">
                    <span class="tomyum-panel__link-icon"><i class="fas fa-robot"></i></span>
                    <span class="tomyum-panel__kicker">Admins and developers</span>
                    <h2>Bots</h2>
                    <p class="tomyum-panel__hint">Make bot accounts for IRC, pick their owners, and change their name, avatar, banner, me! page and passwords.</p>
                </a>
            @endif
            @if ($panels['kudosu'])
                <a class="tomyum-panel__link tomyum-panel__link--big" href="{{ route('panel.kudosu.index') }}">
                    <span class="tomyum-panel__link-icon"><i class="fas fa-medal"></i></span>
                    <span class="tomyum-panel__kicker">Admins and developers</span>
                    <h2>Kudosu</h2>
                    <p class="tomyum-panel__hint">Give or take a player's kudosu, with a reason that shows on their profile.</p>
                </a>
            @endif
            @if ($panels['admin'])
                <a class="tomyum-panel__link tomyum-panel__link--big" href="{{ route('panel.admin.index') }}">
                    <span class="tomyum-panel__link-icon"><i class="fas fa-cog"></i></span>
                    <span class="tomyum-panel__kicker">Administration</span>
                    <h2>Admin panel</h2>
                    <p class="tomyum-panel__hint">The server at a glance, accounts, groups and supporter, and the site's content: news, badges, titles, backgrounds, pools.</p>
                </a>
            @endif
        </div>
    </div>
@endsection
