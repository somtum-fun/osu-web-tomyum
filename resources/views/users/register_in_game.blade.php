{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: sign-up is in the game only (users/create and legacy clients' p/register show this). --}}
@php
    $serverName = $GLOBALS['cfg']['tomyum']['server_name'];
    $rulesUrl = route('legal', ['locale' => app()->getLocale(), 'path' => 'Rules']);
@endphp
@extends('master', ['titlePrepend' => 'Create an account'])

@section('content')
    @include('layout._page_header_v4')

    {{-- TomYum: laid out like the Rules pages (tomyum-legal), so the steps get the same spacing. --}}
    <div class="osu-page osu-page--generic">
        <div class="tomyum-legal">
            <h1 class="tomyum-legal__title">Create your account in the game</h1>

            <div class="tomyum-legal__body tomyum-legal__body--spaced">
                <p>
                    New {{ $serverName }} accounts are made in the game, not on the website. It only takes a minute:
                </p>

                <ol>
                    <li>
                        Get <a href="{{ route('download') }}">lazer-fork (lazer)</a>, or connect a current osu! stable
                        client to {{ $serverName }}, and open it. (The old 2008-2015 builds can't make accounts, but they
                        can sign in with one.)
                    </li>
                    <li>
                        <strong>lazer-fork:</strong> open the sign-in panel at the top right and choose
                        "Create account".<br>
                        <strong>Stable:</strong> click your profile at the top left and choose to create an account.
                    </li>
                    <li>
                        That's it. Use the same username and password to sign in here and in every client, old builds
                        included.
                    </li>
                </ol>

                <p>
                    Please turn off any VPN or proxy before you sign up: sign-ups through them are blocked, and each
                    network (your home, school or phone) can make one account a day. Everyone gets one account; see the
                    <a href="{{ $rulesUrl }}">rules</a>.
                </p>

                <p>
                    Something not working? Email
                    <a href="mailto:{{ App\Libraries\TomYumTickets::address('accounts') }}">{{ App\Libraries\TomYumTickets::address('accounts') }}</a>
                    @if (osu_url('dev') !== null)
                        or ask on our <a href="{{ osu_url('dev') }}">Discord</a>
                    @endif
                    and we'll help you out.
                </p>
            </div>
        </div>
    </div>
@endsection
