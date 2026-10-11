{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: a legal page from our wiki (Legal/<name>), picked in Admin panel > Branding. --}}
@extends('tomyum-legal.layout')

@php
    $content = $wikiPage->get();
    $updated = $content['header']['last_updated'] ?? null;
@endphp

@section('legal-title', $wikiPage->title())

@section('legal-updated')
    @if ($updated !== null)
        Last updated: {{ i18n_date(Carbon\Carbon::parse($updated)) }} ·
    @endif
    <a href="{{ $wikiPage->editUrl() }}" target="_blank">See it on GitHub</a>
    @if (priv_check('WikiPageRefresh')->can())
        · <a href="{{ route('legal', ['locale' => $wikiPage->locale, 'path' => $path]) }}" data-remote="true" data-method="PUT">Refresh</a>
    @endif
@endsection

@section('legal-body')
    {{-- The page's own title is shown above, so not twice. --}}
    {!! preg_replace('#<h1\b[^>]*>.*?</h1>#s', '', $content['output'], 1) !!}
@endsection
