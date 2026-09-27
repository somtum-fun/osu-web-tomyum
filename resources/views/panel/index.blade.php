{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Staff panel'])

@section('content')
    @include('panel._header')
    <div class="osu-page osu-page--admin">
        <div class="tomyum-panel__links">
            @if ($panels['bn'])
                <a class="tomyum-panel__link" href="{{ route('panel.bn.index') }}">
                    <h2>BN panel</h2>
                    <p class="tomyum-panel__hint">Import beatmap sets from bancho and manage their ranked status.</p>
                </a>
            @endif
            @if ($panels['gmt'])
                <a class="tomyum-panel__link" href="{{ route('panel.gmt.index') }}">
                    <h2>GMT panel</h2>
                    <p class="tomyum-panel__hint">Restrictions, silences, reports, chat logs and score removal.</p>
                </a>
            @endif
            @if ($panels['admin'])
                <a class="tomyum-panel__link" href="{{ route('panel.admin.index') }}">
                    <h2>Admin panel</h2>
                    <p class="tomyum-panel__hint">Server dashboard, and editing accounts: name, email, country, password, groups, supporter.</p>
                </a>
            @endif
        </div>
    </div>
@endsection
