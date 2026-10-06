{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: this server's own legal pages (LegalController), in place of ppy's. --}}
@extends('master')

@php
    $legalPages = [
        'rules' => 'Rules',
        'restrictions' => 'Restrictions & Appeals',
        'terms' => 'Terms of Service',
        'privacy' => 'Privacy Policy',
        'copyright' => 'Copyright (DMCA)',
    ];
@endphp

@section('content')
@component('layout._page_header_v4')
@endcomponent

<div class="osu-page osu-page--generic">
    <div class="tomyum-legal">
        <nav class="tomyum-legal__nav">
            @foreach ($legalPages as $key => $title)
                <a
                    class="tomyum-legal__nav-link {{ $key === $page ? 'tomyum-legal__nav-link--active' : '' }}"
                    href="{{ route('legal', ['locale' => app()->getLocale(), 'path' => ucfirst($key)]) }}"
                >{{ $title }}</a>
            @endforeach
        </nav>

        <h1 class="tomyum-legal__title">{{ $legalPages[$page] }}</h1>
        <p class="tomyum-legal__updated">@yield('legal-updated', 'Last updated: 5 October 2026')</p>

        <div class="tomyum-legal__notice">
            {{ App\Libraries\TomYumBranding::name() }} is an unofficial, fan-run osu! private server. It is not affiliated with, endorsed by
            or connected to ppy Pty Ltd. osu! is a trademark of ppy Pty Ltd.
        </div>

        <div class="tomyum-legal__body">
            @yield('legal-body')
        </div>
    </div>
</div>
@endsection
